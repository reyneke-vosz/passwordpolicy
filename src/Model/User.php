<?php

namespace OxidProfessionalServices\PasswordPolicy\Model;

use OxidEsales\Eshop\Application\Controller\ForgotPasswordController;
use OxidEsales\Eshop\Core\Exception\UserException;
use OxidEsales\Eshop\Core\InputValidator;
use OxidEsales\Eshop\Core\Registry;
use OxidProfessionalServices\PasswordPolicy\Core\PasswordPolicyConfig;
use OxidProfessionalServices\PasswordPolicy\Core\PasswordPolicyValidator;
use RateLimit\ApcuRateLimiter;
use RateLimit\Exception\LimitExceeded;
use RateLimit\Rate;

class User extends User_parent
{
    /**
     * Method is used for overriding and add additional actions when logging in.
     *
     * @param string $userName
     * @param string $password
     * @throws UserException
     */
    public function onLogin($userName, $password)
    {
        /** @var PasswordPolicyValidator $passValidator */
        $passValidator = oxNew(InputValidator::class);
        if (!isAdmin() && $this->isLoaded() && $err = $passValidator->validatePassword($userName, $password)) {
            $forgotPass = new ForgotPasswordController();
            $forgotPass->forgotPassword();
            $errorMessage = $err->getMessage() . '&nbsp' . Registry::getLang()->translateString('REQUEST_PASSWORD_AFTERCLICK');
            throw oxNew(UserException::class, $errorMessage);
        }
        parent::onLogin($userName, $password);
    }

    public function login($userName, $password, $setSessionCookie = false)
    {
        $rateLimiter = new ApcuRateLimiter();
        $config = new PasswordPolicyConfig();
        try {
            $rateLimiter->limit($userName, Rate::perMinute($config->getRateLimit()));
        } catch (LimitExceeded $exception) {
            throw oxNew(UserException::class, 'OXPS_PASSWORDPOLICY_RATELIMIT_EXCEEDED');
        }
        parent::login($userName, $password, $setSessionCookie);
    }
}
