<?php

namespace App\Services\DomainAuthentication;

/**
 * Une anomalie relevée sur un enregistrement d'authentification.
 *
 * Le message n'est pas figé ici : seuls le code et ses paramètres sont
 * conservés, pour que l'affichage reste traduisible dans les deux langues même
 * si l'analyse a été faite des semaines plus tôt.
 */
class Finding
{
    public const ERROR = 'error';
    public const WARNING = 'warning';
    public const INFO = 'info';

    public function __construct(
        public readonly string $level,
        public readonly string $code,
        public readonly array $params = [],
    ) {
    }

    public static function error(string $code, array $params = []): self
    {
        return new self(self::ERROR, $code, $params);
    }

    public static function warning(string $code, array $params = []): self
    {
        return new self(self::WARNING, $code, $params);
    }

    public static function info(string $code, array $params = []): self
    {
        return new self(self::INFO, $code, $params);
    }

    public function toArray(): array
    {
        return [
            'level' => $this->level,
            'code' => $this->code,
            'params' => $this->params,
        ];
    }
}
