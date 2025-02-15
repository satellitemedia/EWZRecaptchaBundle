<?php

namespace EWZ\Bundle\RecaptchaBundle\Validator\Constraints;

use ReCaptcha\ReCaptcha;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class IsTrueValidator extends ConstraintValidator
{
    /**
     * Enable recaptcha?
     *
     * @var bool
     */
    protected $enabled;

    /**
     * Recaptcha.
     *
     * @var ReCaptcha
     */
    protected $recaptcha;

    /**
     * Request Stack.
     *
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * Enable serverside host check.
     *
     * @var bool
     */
    protected $verifyHost;

    /**
     * Authorization Checker.
     *
     * @var AuthorizationChecker
     */
    protected $authorizationChecker;

    /**
     * Trusted Roles.
     *
     * @var array
     */
    protected $trustedRoles;

    /**
     * @param bool                               $enabled
     * @param ReCaptcha                          $recaptcha
     * @param RequestStack                       $requestStack
     * @param bool                               $verifyHost
     * @param AuthorizationCheckerInterface|null $authorizationChecker
     * @param array                              $trustedRoles
     */
    public function __construct(
        bool $enabled,
        ReCaptcha $recaptcha,
        RequestStack $requestStack,
        bool $verifyHost,
        ?AuthorizationCheckerInterface $authorizationChecker = null,
        array $trustedRoles = array())
    {
        $this->enabled = $enabled;
        $this->recaptcha = $recaptcha;
        $this->requestStack = $requestStack;
        $this->verifyHost = $verifyHost;
        $this->authorizationChecker = $authorizationChecker;
        $this->trustedRoles = $trustedRoles;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint): void
    {
        // if recaptcha is disabled, always valid
        if (!$this->enabled) {
            return;
        }

        // if we have an authorized role
        if ($this->authorizationChecker
            && count($this->trustedRoles) > 0
            && $this->authorizationChecker->isGranted($this->trustedRoles)) {
            return;
        }

        if (\is_callable([$this->requestStack, 'getMainRequest'])) {
            $request = $this->requestStack->getMainRequest();   // symfony 5.3+
        } else {
            $request = $this->requestStack->getMasterRequest();
        }

        $remoteip = $request->getClientIp();
        // define variable for recaptcha check answer
        $answer = $request->get('g-recaptcha-response', $value);

        // Verify user response with Google
        $response = $this->recaptcha->verify($answer, $remoteip);

        if (!$response->isSuccess()) {
            $this->context->addViolation($constraint->message);
        }
        // Perform server side hostname check
        elseif ($this->verifyHost && $response->getHostname() !== $request->getHost()) {
            $this->context->addViolation($constraint->invalidHostMessage);
        }
    }
}
