<?php

declare (strict_types=1);
namespace OCA\Notifications\Vendor\Jose\Component\Signature;

use OCA\Notifications\Vendor\Jose\Component\Core\AlgorithmManagerFactory;
class JWSVerifierFactory
{
    public function __construct(private readonly AlgorithmManagerFactory $algorithmManagerFactory)
    {
    }
    /**
     * Creates a JWSVerifier using the given signature algorithm aliases.
     *
     * @param string[] $algorithms
     */
    public function create(array $algorithms): JWSVerifier
    {
        $algorithmManager = $this->algorithmManagerFactory->create($algorithms);
        return new JWSVerifier($algorithmManager);
    }
}