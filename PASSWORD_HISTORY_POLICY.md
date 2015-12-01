# Password history policy [![Build Status](https://travis-ci.org/nkostadinov/yii2-user.svg?branch=master)](https://travis-ci.org/nkostadinov/yii2-user) 

When a user wants to change his password, he must add a password that hasn't been used by him in the past.
This functionality checks the last 5 password changes. The value is configurable.

# Installation

In order to install the functionality, you must add the password history policy behavior to your ChangePasswordForm model in your config (config.php/web.php).

This is the most simple configuration needed:

```
'user' => [
    'class' => 'nkostadinov\user\components\User',
    'changePasswordForm' => [
        'class' => 'nkostadinov\user\models\forms\ChangePasswordForm',
        'as passwordHistoryPolicy' => [ // Or whatever name you choose
            'class' => 'nkostadinov\user\behaviors\PasswordHistoryPolicyBehavior',
        ],
    ],
],
```

If you want to change the number of passwords checked, add one more line:

```
'user' => [
    'class' => 'nkostadinov\user\components\User',
    'changePasswordForm' => [
        'class' => 'nkostadinov\user\models\forms\ChangePasswordForm',
        'as passwordHistoryPolicy' => [ // Or whatever name you choose
            'class' => 'nkostadinov\user\behaviors\PasswordHistoryPolicyBehavior',
            'lastPasswordChangesCount' => 10, // Check the last 10 passwords
        ],
    ],
],
```