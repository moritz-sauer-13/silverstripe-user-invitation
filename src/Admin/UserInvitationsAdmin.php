<?php

namespace Dynamic\SilverStripe\UserInvitations\Admin;

/**
 * Created by PhpStorm.
 * User: tn
 * Date: 06/06/2023
 * Time: 12:58
 */

use Dynamic\SilverStripe\UserInvitations\Model\UserInvitation;
use SilverStripe\Admin\ModelAdmin;

class UserInvitationsAdmin extends ModelAdmin
{

    private static array $managed_models = [
        UserInvitation::class
    ];

    private static string $url_segment = 'userinvite-admin';

    private static string $menu_title = 'User invite';

    private static string $menu_icon_class = 'font-icon-torso';
}
