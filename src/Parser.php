<?php
/**
 * This file is part of Lcobucci\JWT, a simple library to handle JWT and JWS
 *
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 */

namespace Lcobucci\JWT;

use DateTimeImmutable;
use InvalidArgumentException;
use Lcobucci\JWT\Claim\Factory as ClaimFactory;
use Lcobucci\JWT\Parsing\Decoder;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Token\RegisteredClaims;
use Lcobucci\JWT\Token\UnsupportedHeaderFound;
use RuntimeException;
use function array_key_exists;

/**
 * This class parses the JWT strings and convert them into tokens
 *
 * @author Luís Otávio Cobucci Oblonczyk <lcobucci@gmail.com>
 * @since 0.1.0
 */
class Parser
{
    /**
     * The data decoder
     *
     * @var Decoder
     */
    private $decoder;

    /**
     * Initializes the object
     *
     * @param Decoder $decoder
     */
    public function __construct(Decoder $decoder = null)
    {
        $this->decoder = $decoder ?: new Decoder();
    }

    /**
     * Parses the JWT and returns a token
     *
     * @param string $jwt
     *
     * @return Token
     *
     * @throws InvalidArgumentException  When JWT is not a string or is invalid.
     * @throws RuntimeException          When something goes wrong while decoding
     */
    public function parse($jwt)
    {
        $data = $this->splitJwt($jwt);
        $header = $this->parseHeader($data[0]);
        $claims = $this->parseClaims($data[1]);
        $signature = $this->parseSignature($header, $data[2]);

        foreach ($claims as $name => $value) {
            if (isset($header[$name])) {
                $header[$name] = $value;
            }
        }

        if ($signature === null) {
            unset($data[2]);
        }

        return new Token($header, $claims, $signature, $data);
    }

    /**
     * Splits the JWT string into an array
     *
     * @param string $jwt
     *
     * @return array
     *
     * @throws InvalidArgumentException When JWT is not a string or is invalid
     */
    protected function splitJwt($jwt)
    {
        if (!is_string($jwt)) {
            throw InvalidTokenStructure::missingOrNotEnoughSeparators();
        }

        $data = explode('.', $jwt);

        if (count($data) != 3) {
            throw InvalidTokenStructure::missingOrNotEnoughSeparators();
        }

        return $data;
    }

    /**
     * Parses the header from a string
     *
     * @param string $data
     *
     * @return array
     *
     * @throws UnsupportedHeaderFound When an invalid header is informed
     */
    protected function parseHeader($data)
    {
        $header = (array) $this->decoder->jsonDecode($this->decoder->base64UrlDecode($data));

        if (isset($header['enc'])) {
            throw UnsupportedHeaderFound::encryption();
        }

        return $this->convertToDateObjects($header);
    }

    /**
     * Parses the claim set from a string
     *
     * @param string $data
     *
     * @return array
     */
    protected function parseClaims($data)
    {
        $claims = (array) $this->decoder->jsonDecode($this->decoder->base64UrlDecode($data));

        return $this->convertToDateObjects($claims);
    }

    /**
     * @param array<string, mixed> $items
     *
     * @return array<string, mixed>
     */
    private function convertToDateObjects(array $items)
    {
        foreach (RegisteredClaims::DATE_CLAIMS as $name) {
            if (! array_key_exists($name, $items)) {
                continue;
            }

            $items[$name] = new DateTimeImmutable('@' . ((int) $items[$name]));
        }

        return $items;
    }

    /**
     * Returns the signature from given data
     *
     * @param array $header
     * @param string $data
     *
     * @return Signature
     */
    protected function parseSignature(array $header, $data)
    {
        if ($data == '' || !isset($header['alg']) || $header['alg'] == 'none') {
            return null;
        }

        $hash = $this->decoder->base64UrlDecode($data);

        return new Signature($hash);
    }
}
