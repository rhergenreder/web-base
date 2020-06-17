<?php


namespace Api\User;


use Api\Parameter\StringType;
use Api\SendMail;
use Api\User\Create;

class Register extends Create{
    public function __construct($user, $externalCall = false) {
        parent::__construct($user, $externalCall);
    }

    public function execute($values = array()) {
        if ($this->user->isLoggedIn()) {
            $this->lastError = L('You are already logged in');
            $this->success = false;
            return false;
        }

        if (!parent::execute($values)) {
            return false;
        }

        if($this->success) {
            $email = $this->getParam('email');
            $token = generateRandomString(36);
            $request = new SendMail($this->user);
            $link = "http://localhost/acceptInvitation?token=$token";
            $this->success = $request->execute(array(
                    "from" => "webmaster@romanh.de",
                    "to" => $email,
                    "subject" => "Account Invitation for web-base@localhost",
                    "body" =>
                        "Hello,<br>
you were invited to create an account on web-base@localhost. Click on the following link to confirm the registration, it is 48h valid from now.             
If the invitation was not intended, you can simply ignore this email.<br><br><a href=\"$link\">$link</a>"
                )
            );
            $this->lastError  = $request->getLastError();
        }
        return $this->success;
    }
}