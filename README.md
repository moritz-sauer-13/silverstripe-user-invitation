# SilverStripe User Invitation

This module adds the ability to invite users to a secure website (e.g. Intranet or Extranet).

[![CI](https://github.com/dynamic/silverstripe-user-invitation/actions/workflows/ci.yml/badge.svg)](https://github.com/dynamic/silverstripe-user-invitation/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/dynamic/silverstripe-user-invitation/branch/master/graph/badge.svg)](https://codecov.io/gh/dynamic/silverstripe-user-invitation)

[![Latest Stable Version](https://poser.pugx.org/dynamic/silverstripe-user-invitation/v/stable)](https://packagist.org/packages/dynamic/silverstripe-user-invitation)
[![Total Downloads](https://poser.pugx.org/dynamic/silverstripe-user-invitation/downloads)](https://packagist.org/packages/dynamic/silverstripe-user-invitation)
[![Latest Unstable Version](https://poser.pugx.org/dynamic/silverstripe-user-invitation/v/unstable)](https://packagist.org/packages/dynamic/silverstripe-user-invitation)
[![License](https://poser.pugx.org/dynamic/silverstripe-user-invitation/license)](https://packagist.org/packages/dynamic/silverstripe-user-invitation)

## Requirements

 * silverstripe/framework ^4

 ## Installation

```
composer require dynamic/silverstripe-user-invitation
```

## License

See [License](LICENSE.md)

## Features

* Quick-entry invitation form (By default only first name and email fields are required to invite someone)
* Sends email invitations to recipient
* Supports optional user group assignment (See below for how to enforce this group selection) 
* Invitation expiry can be set via configuration.
* Default SilverStripe member validation is applied.

### Force required user group assignment
Place the following in your mysite/_config/config.yml
```yml
Dynamic\SilverStripe\UserInvitations\Model\UserInvitation:
    force_require_group: true
```

### Template override
To update the base template use `updateMainTemplates`. It defaults to `Page`.

```php
/**
 * @param array $mainTemplates
 */
public function updateMainTemplates(&$mainTemplates)
{
    array_unshift($mainTemplates, 'InvitationPage');
}
```

## Maintainers

 *  [Dynamic](https://www.dynamicagency.com) (<dev@dynamicagency.com>)

## Credits
Forked from [FSWebWorks/silverstripe-user-invitation](https://github.com/FSWebWorks/silverstripe-user-invitation) to upgrade for Silverstripe 4 & 5.

## Bugtracker
Bugs are tracked in the issues section of this repository. Before submitting an issue please read over
existing issues to ensure yours is unique.

If the issue does look like a new bug:

 - Create a new issue
 - Describe the steps required to reproduce your issue, and the expected outcome. Unit tests, screenshots
 and screencasts can help here.
 - Describe your environment as detailed as possible: SilverStripe version, Browser, PHP version,
 Operating System, any installed SilverStripe modules.

Please report security issues to the module maintainers directly. Please don't file security issues in the bugtracker.

## Development and contribution
If you would like to make contributions to the module please ensure you raise a pull request and discuss with the module maintainers.
