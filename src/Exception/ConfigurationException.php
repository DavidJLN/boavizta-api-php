<?php

declare(strict_types=1);

namespace Boavizta\Api\Exception;

/**
 * The client cannot be built: no PSR-18 client or PSR-17 factory found, or no way to set the timeout.
 */
final class ConfigurationException extends BoaviztaException
{
}
