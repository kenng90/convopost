<?php

namespace Modules\Social\Publishing;

use InvalidArgumentException;
use Modules\Social\Contracts\SocialPublisherInterface;
use Modules\Social\Enums\SocialProvider;

class SocialPublisherManager
{
    public function for(SocialProvider|string $provider): SocialPublisherInterface
    {
        $enum = $provider instanceof SocialProvider
            ? $provider
            : SocialProvider::tryFromString($provider);

        if ($enum === null) {
            throw new InvalidArgumentException('Unknown social provider: '.(string) $provider);
        }

        $class = config('social.providers.'.$enum->value.'.publisher');

        if (! is_string($class) || $class === '' || ! class_exists($class)) {
            return new NullSocialPublisher($enum);
        }

        $instance = app($class);

        if (! $instance instanceof SocialPublisherInterface) {
            return new NullSocialPublisher($enum);
        }

        return $instance;
    }
}
