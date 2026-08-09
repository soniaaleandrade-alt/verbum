<?php

declare(strict_types=1);

namespace VerbumStudio\Auth;

final class Capabilities
{
    public const ACCESS = 'verbum_access';
    public const MANAGE = 'verbum_manage';
    public const MANAGE_SETTINGS = 'verbum_manage_settings';

    /** @return string[] */
    public function all(): array
    {
        return [self::ACCESS, self::MANAGE, self::MANAGE_SETTINGS];
    }

    public function add(): void
    {
        $administrator = get_role('administrator');
        if ($administrator) {
            foreach ($this->all() as $capability) {
                $administrator->add_cap($capability);
            }
        }

        $editor = get_role('editor');
        if ($editor) {
            $editor->add_cap(self::ACCESS);
        }
    }

    public function currentUserCanAccess(): bool
    {
        return is_user_logged_in() && current_user_can(self::ACCESS);
    }

    public function currentUserIsAdmin(): bool
    {
        return is_user_logged_in() && current_user_can(self::MANAGE_SETTINGS);
    }
}
