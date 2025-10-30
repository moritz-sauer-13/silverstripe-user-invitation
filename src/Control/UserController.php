<?php

namespace Dynamic\SilverStripe\UserInvitations\Control;

use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\Model\ModelData;
use SilverStripe\ORM\FieldType\DBHTMLText;
use Page;
use SilverStripe\Model\ArrayData;
use Dynamic\SilverStripe\UserInvitations\Model\UserInvitation;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\ConfirmedPasswordField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\ListboxField;
use SilverStripe\Forms\TextField;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Security;
use SilverStripe\View\SSViewer;
use SilverStripe\View\ThemeResourceLoader;

class UserController extends Controller implements PermissionProvider
{

    private static array $allowed_actions = [
        'index',
        'accept',
        'success',
        'InvitationForm',
        'AcceptForm',
        'expired',
        'notfound',
    ];

    public function providePermissions()
    {
        return [
            'ACCESS_USER_INVITATIONS' => [
                'name' => _t(
                    'UserController.ACCESS_PERMISSIONS',
                    'Allow user invitations'
                ),
                'category' => _t(
                    'UserController.CMS_ACCESS_CATEGORY',
                    'User Invitations'
                ),
            ],
        ];
    }

    protected function init()
    {
        parent::init();

        $action = $this->getRequest()->param('Action');

        if (!Security::getCurrentUser() && ($action === 'index' || $action === 'InvitationForm')) {
            $security = Injector::inst()->get(Security::class);
            $link = $security->Link('login');
            return $this->redirect(Controller::join_links(
                $link,
                '?BackURL=' . $this->Link('index')
            ));
        }

        return null;
    }

    public function index(): HTTPResponse|DBHTMLText
    {
        if (!Permission::check('ACCESS_USER_INVITATIONS')) {
            return Security::permissionFailure();
        }

        return $this->renderWithLayout(static::class);
    }

    public function InvitationForm()
    {
        $groups = Group::get()->map(
            'Code',
            'Title'
        )->toArray();
        $fields = FieldList::create(
            TextField::create(
                'FirstName',
                _t('UserController.INVITE_FIRSTNAME', 'First name:')
            ),
            EmailField::create(
                'Email',
                _t('UserController.INVITE_EMAIL', 'Invite email:')
            ),
            ListboxField::create(
                'Groups',
                _t('UserController.INVITE_GROUP', 'Add to group'),
                $groups
            )
                ->setRightTitle(_t(
                    'UserController.INVITE_GROUP_RIGHTTITLE',
                    'Ctrl + click to select multiple'
                ))
        );
        $actions = FieldList::create(
            FormAction::create(
                'sendInvite',
                _t('UserController.SEND_INVITATION', 'Send Invitation')
            )
        );
        $requiredFields = RequiredFieldsValidator::create('FirstName', 'Email');

        if (UserInvitation::config()->get('force_require_group')) {
            $requiredFields->addRequiredField('Groups');
        }

        $form = Form::create($this, 'InvitationForm', $fields, $actions, $requiredFields);
        $this->extend('updateInvitationForm', $form);
        return $form;
    }

    /**
     * Records and sends the user's invitation
     * @param $data
     * @return bool|HTTPResponse
     */
    public function sendInvite(array $data, Form $form): HTTPResponse
    {
        if (!Permission::check('ACCESS_USER_INVITATIONS')) {
            $form->sessionMessage(
                _t(
                    'UserController.PERMISSION_FAILURE',
                    "You don't have permission to create user invitations"
                ),
                'bad'
            );
            return $this->redirectBack();
        }
        
        if (!$form->validate()->isValid()) {
            $form->sessionMessage(
                _t(
                    'UserController.SENT_INVITATION_VALIDATION_FAILED',
                    'At least one error occured while trying to save your invite: {error}',
                    ['error' => $form->getValidator()->getErrors()[0]['fieldName']]
                ),
                'bad'
            );
            return $this->redirectBack();
        }

        $invite = UserInvitation::create();
        $form->saveInto($invite);
        // We now store the values as json, same as the backend
        $groups = json_encode(array_values($data['Groups']));

        $invite->Groups = $groups;
        try {
            $invite->write();
        } catch (ValidationException $validationException) {
            $form->sessionMessage(
                $validationException->getMessage(),
                'bad'
            );
            return $this->redirectBack();
        }
        
        $invite->sendInvitation();

        $form->sessionMessage(
            _t(
                'UserController.SENT_INVITATION',
                'An invitation was sent to {email}.',
                ['email' => $data['Email']]
            ),
            'good'
        );
        return $this->redirectBack();
    }

    public function accept()
    {
        if (!$hash = $this->getRequest()->param('ID')) {
            return $this->forbiddenError();
        }
        
        if ($invite = UserInvitation::get()->filter(
            'TempHash',
            $hash
        )->first()) {
            if ($invite->isExpired()) {
                return $this->redirect($this->Link('expired'));
            }
        } else {
            return $this->redirect($this->Link('notfound'));
        }
        
        return $this->renderWithLayout([
            static::class . '_accept',
            static::class,
        ], [
            'Invite' => $invite,
        ]);
    }

    public function AcceptForm()
    {
        $hash = $this->getRequest()->param('ID');
        $invite = UserInvitation::get()->filter('TempHash', $hash)->first();
        $firstName = ($invite) ? $invite->FirstName : '';

        $fields = FieldList::create(
            TextField::create(
                'FirstName',
                _t('UserController.ACCEPTFORM_FIRSTNAME', 'First name:'),
                $firstName
            ),
            TextField::create(
                'Surname',
                _t('UserController.ACCEPTFORM_SURNAME', 'Surname:')
            ),
            ConfirmedPasswordField::create('Password'),
            HiddenField::create('HashID')->setValue($hash)
        );
        $actions = FieldList::create(
            FormAction::create(
                'saveInvite',
                _t('UserController.ACCEPTFORM_REGISTER', 'Register')
            )
        );
        $requiredFields = RequiredFieldsValidator::create('FirstName', 'Surname');
        $form = Form::create($this, 'AcceptForm', $fields, $actions, $requiredFields);
        $this->extend('updateAcceptForm', $form, $invite);
        return $form;
    }

    /**
     * @param $data
     * @return bool|SS_HTTPResponse
     */
    public function saveInvite(array $data, Form $form): HTTPResponse
    {
        if (!$invite = UserInvitation::get()->filter(
            'TempHash',
            $data['HashID']
        )->first()) {
            return $this->notFoundError();
        }
        
        if ($form->validate()->isValid()) {
            $member = Member::create(['Email' => $invite->Email]);
            $form->saveInto($member);

            $this->extend('updateMemberCreate', $member, $invite);

            try {
                if ($member->validate()) {
                    $member->write();

                    $groups = json_decode($invite->Groups);

                    // Add user group info
                                        foreach (Group::get()->filter(['Code' => $groups]) as $group) {
                        $group->Members()->add($member);
                    }
                }
            } catch (ValidationException $e) {
                $form->sessionMessage(
                    $e->getMessage(),
                    'bad'
                );
                return $this->redirectBack();
            }
            
            // Delete invitation
            $invite->delete();
            return $this->redirect($this->Link('success'));
        }

        $form->sessionMessage(
            Convert::array2json($form->getValidator()->getErrors()),
            'bad'
        );
        return $this->redirectBack();
    }

    public function success(): DBHTMLText
    {
        $security = Injector::inst()->get(Security::class);

        $link = 'login';
        $back_url = Config::inst()->get(UserController::class, 'back_url');
        $link = ($back_url) ? $link . '?BackURL=' . $back_url: $link ;

        return $this->renderWithLayout(
            [
                static::class . '_success',
                static::class,
            ],
            [
                'LoginLink' => $security->Link($link),
            ]
        );
    }

    public function expired(): DBHTMLText
    {
        return $this->renderWithLayout([
            static::class . '_expired',
            static::class,
        ]);
    }

    public function notfound(): DBHTMLText
    {
        return $this->renderWithLayout([
            static::class . '_notfound',
            static::class,
        ]);
    }

    private function forbiddenError()
    {
        return $this->httpError(403, _t(
            'UserController.403_NOTICE',
            'You must be logged in to access this page.'
        ));
    }

    private function notFoundError(): HTTPResponse
    {
        return $this->redirect($this->Link('notfound'));
    }

    /**
     * Ensure that links for this controller use the customised route.
     * Searches through the rules set up for the class and returns the first route.
     *
     * @param string $action
     * @return string
     */
    public function Link($action = null)
    {
        if ($url = array_search(
            get_called_class(),
            (array)Config::inst()->get(Director::class, 'rules'),
            true
        )) {
            // Check for slashes and drop them
            if ($indexOf = stripos($url, '/')) {
                $url = substr($url, 0, $indexOf);
            }
            
            return $this->join_links($url, $action);
        }

        return null;
    }

    /**
     * @param array|string $templates
     * @param array|ArrayData $customFields
     */
    public function renderWithLayout($templates, array|ModelData $customFields = []): DBHTMLText
    {
        $templates = $this->getLayoutTemplates($templates);
        $mainTemplates = [Page::class];
        $this->extend('updateMainTemplates', $mainTemplates);

        $viewer = SSViewer::create($this->getViewerTemplates());
        $viewer->setTemplateFile(
            'main',
            ThemeResourceLoader::inst()->findTemplate($mainTemplates)
        );
        $viewer->setTemplateFile(
            'Layout',
            ThemeResourceLoader::inst()->findTemplate($templates)
        );

        //print_r($viewer->templates());
        return $viewer->process(
            $this->customise($customFields)
        );
    }

    /**
     * @param array|string $templates
     */
    public function getLayoutTemplates($templates): array
    {
        if (is_string($templates)) {
            $templates = [$templates];
        }

        // Always include page template as fallback
        if (count($templates) === 0 || $templates[count($templates) - 1] !== 'Page') {
            $templates[] = 'Page';
        }
        
        // otherwise it renders funny
        $templates = ['type' => 'Layout'] + $templates;
        return $templates;
    }
}
