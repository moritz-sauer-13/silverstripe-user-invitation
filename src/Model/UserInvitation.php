<?php

namespace Dynamic\SilverStripe\UserInvitations\Model;

use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\Core\Validation\ValidationResult;
use LeKoala\CmsActions\CustomAction;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\RandomGenerator;
use SilverStripe\Security\Security;

/**
 * Class UserInvitation
 * @package Dynamic
 * @subpackage UserInvitation
 *
 * @property string FirstName
 * @property string Email
 * @property string TempHash
 * @property string Groups
 * @property int InvitedByID
 * @property Member InvitedBy
 *
 */
class UserInvitation extends DataObject
{
    private static string $table_name = "UserInvitation";

    /**
     * Used to control whether a group selection on the invitation form is required.
     */
    private static bool $force_require_group = false;

    private static array $db = [
        'FirstName' => 'Varchar',
        'Email' => 'Varchar(254)',
        'TempHash' => 'Varchar',
        'Groups' => 'Text'
    ];

    private static array $has_one = [
        'InvitedBy' => Member::class
    ];

    private static array $indexes = [
        'Email' => true,
        'TempHash' => true
    ];

    public function summaryFields()
    {
        return [
            'FirstName' => _t('UserInvitation.FirstName', 'First Name'),
            'Email' => _t('UserInvitation.Email', 'Email'),
            'InvitedBy.FirstName' => _t('UserInvitation.InvitedBy', 'Invited By'),
            'Created' => _t('UserInvitation.Created', 'Created')
        ];
    }

    /**
     * Removes the hash field from the list.
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['TempHash']);

        $groups = Group::get()->map('Code', 'Title')->toArray();
        $fields->addFieldsToTab('Root.Main', [
            TextField::create('FirstName', _t('SilverStripe\Security\Member.FIRSTNAME')),
            EmailField::create('Email', _t('SilverStripe\Security\Member.EMAIL')),

            CheckboxSetField::create(
                'Groups',
                _t('UserController.INVITE_GROUP', 'Add to group'),
                $groups
            )
        ]);

        $fields->replaceField('Email', EmailField::create('Email'));

        $fields->addFieldToTab('Root.Main', ReadonlyField::create('TempHash'));
        $fields->replaceField('InvitedByID',
            $fields->dataFieldByName('InvitedByID')
                ->performReadonlyTransformation()
                ->setTitle(_t('UserInvitation.InvitedBy', 'Invited by')));
        return $fields;
    }

    protected function onBeforeWrite()
    {
        if (!$this->ID) {
            $generator = RandomGenerator::create();
            $this->TempHash = $generator->randomToken('sha1');

            if (Security::getCurrentUser()) {
                $currentUserID = Member::get()->byID(Security::getCurrentUser()->ID);
                if ($currentUserID) {
                    $this->InvitedByID = $currentUserID->ID;
                }
            }
        }
        
        parent::onBeforeWrite();
    }

    /**
     * Sends an invitation to the desired user
     */
    public function sendInvitation()
    {
        $email = Email::create()
            ->setFrom(Email::config()->get('admin_email'))
            ->setTo($this->Email)
            ->setSubject(
                _t(
                    'UserInvation.EMAIL_SUBJECT',
                    'Invitation from {name}',
                    ['name' => $this->InvitedBy()->FirstName]
                )
            )->setHTMLTemplate('email/UserInvitationEmail')
            ->setData(
                [
                    'Invite' => $this,
                    'SiteURL' => Director::absoluteBaseURL(),
                ]
            );

        $this->extend('updateInvitationEmail', $email);

        $email->send();

        return $email;
    }

    public function getCMSValidator(): RequiredFieldsValidator
    {
        return RequiredFieldsValidator::create([
            'FirstName',
            'Email'
        ]);
    }

    /**
     * Checks if a user invite was already sent, or if a user is already a member
     */
    public function validate(): ValidationResult
    {
        $valid = parent::validate();
        $exists = $this->isInDB();

        if (!$exists) {
            if (self::get()->filter('Email', $this->Email)->first()) {
                // UserInvitation already sent
                $valid->addError(_t('UserInvitation.INVITE_ALREADY_SENT', 'This user was already sent an invite.'));
            }

            if (Member::get()->filter('Email', $this->Email)->first()) {
                // Member already exists
                $valid->addError(_t(
                    'UserInvitation.MEMBER_ALREADY_EXISTS',
                    'This person is already a member of this system.'
                ));
            }
        }
        
        return $valid;
    }

    /**
     * Checks if this invitation has expired
     * @return bool
     */
    public function isExpired()
    {
        $result = false;
        $days = self::config()->get('days_to_expiry');
        $time = DBDatetime::now()->getTimestamp();
        $ago = abs($time - strtotime($this->LastEdited));
        $rounded = round($ago / 86400);
        if ($rounded > $days) {
            return true;
        }
        
        return $result;
    }

    public function canCreate($member = null, $context = null)
    {
        return Permission::check('ACCESS_USER_INVITATIONS');
    }
    
    public function getCMSActions()
    {
        $actions = parent::getCMSActions();

        if ($this->isInDB()) {
            $actions->push(CustomAction::create("doCustomActionSendInvitation", _t('UserInvitation.SendInvitation', 'Send invitation')));
        } else {
            $actions->push(LiteralField::create('doCustomActionSendInvitationUnavailable', '<span class="bb-align">' . _t('UserInvitation.CreateSaveBeforeSending', 'Create/Save before sending invite!')."</span>"));
        }

        return $actions;
    }

    public function doCustomActionSendInvitation() {

        if ($email = $this->sendInvitation()) {
            return $email;
        }

        return 'Invite was NOT send';
    }
}
