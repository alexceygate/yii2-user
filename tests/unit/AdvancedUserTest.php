<?php

use nkostadinov\user\behaviors\PasswordHistoryPolicyBehavior;
use nkostadinov\user\models\forms\ChangePasswordForm;
use nkostadinov\user\models\PasswordHistory;
use nkostadinov\user\models\User;
use yii\codeception\TestCase;

class AdvancedUserTest extends TestCase
{   
    use \Codeception\Specify;

    const ATTR_PASSWORD_CHANGED_AT = 'password_changed_at';
    
    const ATTR_PASSWORD_HISTORY = 'password_history';
    const PASSWORD_HISTORY_MODEL = 'nkostadinov\user\models\PasswordHistory';

    public $appConfig = '@tests/tests/_app/config/unit.php';
    
    /**
     * @var UnitTester
     */
    protected $tester;

    protected function tearDown()
    {
        User::deleteAll('email = :email', [':email' => Commons::TEST_EMAIL]);
        parent::tearDown();
    }

    public function testPasswordAging()
    {
        $this->specify('Asure that everything is configured properly', function() {
            verify('Check that the advanced directory exists', is_dir(Commons::ADVANCED_MIGRATIONS_DIR))->true();

            $files = scandir(Commons::ADVANCED_MIGRATIONS_DIR);
            $result = preg_grep('/'. self::ATTR_PASSWORD_CHANGED_AT .'/', $files);
            verify('Check that the migration exists', $result)->notEmpty();

            verify('Check that the field is added to the table (the migration is run)',
                (new User())->hasAttribute(self::ATTR_PASSWORD_CHANGED_AT))->true();
        });

        $this->specify('Behavior validations', function() {
            $behavior = Yii::$app->user->attachBehavior('passwordAging', 'nkostadinov\user\behaviors\PasswordAgingBehavior');
            verify('Check that the behavior exists', $behavior)->notNull();

            verify('Check that passwordChangeInterval field exists', isset($behavior->passwordChangeInterval))->true();
            verify('Check that the default value of passwordChangeInterval is set to two months (in seconds)',
                $behavior->passwordChangeInterval)->equals(60 * 60 * 24 * 30 * 2);
        });
        
        $identity = Commons::createUser(Commons::TEST_EMAIL, 'test');
        $this->specify('Create one user', function() use ($identity) {
            verify('Asure that the password_changed_at field is empty', $identity->password_changed_at)->null();
        });

        $this->specify('Login for a first time', function() use ($identity) {
            Yii::$app->user->login($identity);
            verify('After the first login, the password_changed_at field must be automaticaly set', $identity->password_changed_at)->notNull();
        });
        
        $this->specify('Set the password_changed_at value to a value older than two months', function() use ($identity) {
            $identity->setAttribute(self::ATTR_PASSWORD_CHANGED_AT, strtotime('-3 months'));
            $identity->save('false');
            verify('The login is unsuccessful', Yii::$app->user->login($identity))->false();
        });

        $this->specify('Now set the value of password_changed_at field to 1 month', function() use ($identity) {
            $identity->setAttribute(self::ATTR_PASSWORD_CHANGED_AT, strtotime('-1 month'));
            $identity->save('false');
            
            verify('The login is successful', Yii::$app->user->login($identity))->true();
        });
    }
    
    public function testPasswordHistoryPolicy()
    {
        $this->specify('Asure that everything is configured properly', function() {
            verify('Check that the advanced directory exists', is_dir(Commons::ADVANCED_MIGRATIONS_DIR))->true();

            $files = scandir(Commons::ADVANCED_MIGRATIONS_DIR);
            $result = preg_grep('/'. self::ATTR_PASSWORD_HISTORY .'/', $files);
            verify('Check that the migration exists', $result)->notEmpty();
            
            verify('Check that the table is added to the database (the migration is run)',
                Yii::$app->db->schema->getTableSchema('password_history'))->notNull();

            verify('Asure that the model is created', Yii::createObject(self::PASSWORD_HISTORY_MODEL))->notNull();
        });

        $changePasswordForm = Yii::createObject(Yii::$app->user->changePasswordForm);
        $this->specify('Behavior validations', function() use ($changePasswordForm) {
            $behavior = $changePasswordForm->attachBehavior('passwordHistoryPolicy', 'nkostadinov\user\behaviors\PasswordHistoryPolicyBehavior');
            
            verify('Check that the behavior exists', $behavior)->notNull();
            verify('Check that lastPasswordChangesCount field exists', isset($behavior->lastPasswordChangesCount))->true();
            verify('Check that the default value of lastPasswordChangesCount is set to 5',
                $behavior->lastPasswordChangesCount)->equals(5);
        });

        Commons::createUser(Commons::TEST_EMAIL, 'test123');
        $this->specify('Change the password for a first time by adding the same password', function() use ($changePasswordForm) {
            $changePasswordForm->email = Commons::TEST_EMAIL;
            $changePasswordForm->oldPassword = 'test123';
            $changePasswordForm->newPassword = 'test123';
            $changePasswordForm->newPasswordRepeat = 'test123';

            verify('Assure that the password cannot be changed, because it is the same as the previous one', $changePasswordForm->changePassword())->false();
            verify('Assure that exactly the new password field has errors', $changePasswordForm->hasErrors('newPassword'))->true();
            verify('Assure that the error on the new password is the error we expect',
                $changePasswordForm->getErrors('newPassword')[0])->equals(PasswordHistoryPolicyBehavior::MESSAGE_SAME_PASSWORDS);
        });

        $userId = $changePasswordForm->getUser()->id;
        $this->specify('Change the password this time for real', function() use ($changePasswordForm, $userId) {
            $changePasswordForm->newPassword = 'BabaGusi';
            $changePasswordForm->newPasswordRepeat = 'BabaGusi';
            verify('Assure that the password is successfuly changed', $changePasswordForm->changePassword())->true();

            $previousPasswords = PasswordHistory::findAllByUserId($userId);
            verify('Assure that both - the first and the new passwords are added to the history table',
                count($previousPasswords))->equals(2);
        });        

        $this->specify('Try to change the password by adding a password that has already been used in the past', function() use ($changePasswordForm) {
            $changePasswordForm->oldPassword = 'BabaGusi';
            $changePasswordForm->newPassword = 'test123';
            $changePasswordForm->newPasswordRepeat = 'test123';

            verify('Assure that the password cannot be changed, because it is the same as a password added in the past',
                $changePasswordForm->changePassword())->false();
            verify('Assure that exactly the new password field has errors', $changePasswordForm->hasErrors('newPassword'))->true();
            verify('Assure that the error on the new password is the error we expect',
                $changePasswordForm->getErrors('newPassword')[0])->equals(PasswordHistoryPolicyBehavior::MESSAGE_SAME_PREV_PASSWORDS);
        });

        $this->specify('Change the password for a second time for real', function() use ($changePasswordForm, $userId) {
            $changePasswordForm->oldPassword = 'BabaGusi';
            $changePasswordForm->newPassword = 'AllahuAkbar';
            $changePasswordForm->newPasswordRepeat = 'AllahuAkbar';
            verify('Assure that the password is successfuly changed', $changePasswordForm->changePassword())->true();

            $previousPasswords = PasswordHistory::findAllByUserId($userId);
            verify('Assure that the new password is added to the history table', count($previousPasswords))->equals(3);
        });
    }
}