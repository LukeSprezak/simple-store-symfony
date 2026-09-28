<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Framework\Request\Resolver;

use App\Shared\Infrastructure\Framework\Validator\ValidationError;
use App\Shared\Infrastructure\Utils\Request\RequestInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\PropertyAccess\Exception\RuntimeException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class JsonBodyResolver implements ValueResolverInterface
{
    private const string FORMAT = 'json';
    private const string DEFAULT_JSON = '{}';
    private const string CONTENT_TYPE_JSON = 'application/json';

    public function __construct(
        private SerializerInterface $serializer,
    ) {
    }

    /**
     * @return iterable<RequestInterface>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $type = $argument->getType();

        if (null === $type || !$this->supports($argument)) {
            return;
        }

        if (!$this->isJsonRequest($request)) {
            throw new ValidationError([ValidationError::GENERAL => 'VALIDATION.INVALID_CONTENT_TYPE']);
        }

        $content = $request->getContent() ?: self::DEFAULT_JSON;

        try {
            $payload = $this->serializer->deserialize(
                $content,
                $type,
                self::FORMAT
            );

            if (!$payload instanceof RequestInterface) {
                throw new UnexpectedValueException('The payload must implement RequestInterface.');
            }

            yield $payload;
        } catch (UnexpectedValueException|InvalidArgumentException|RuntimeException $exception) {
            throw new ValidationError([ValidationError::GENERAL => 'VALIDATION.INVALID_PAYLOAD'], $exception);
        }
    }

    private function supports(ArgumentMetadata $argument): bool
    {
        $type = $argument->getType();

        return null !== $type
            && class_exists($type)
            && is_subclass_of($type, RequestInterface::class);
    }

    private function isJsonRequest(Request $request): bool
    {
        $contentType = $request->headers->get('Content-Type', '');

        return str_starts_with($contentType, self::CONTENT_TYPE_JSON);
    }
}
