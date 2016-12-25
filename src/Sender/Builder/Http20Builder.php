<?php

/*
 * This file is part of the AppleApnPush package
 *
 * (c) Vitaliy Zhuk <zhuk2205@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Apple\ApnPush\Sender\Builder;

use Apple\ApnPush\Encoder\MessageEncoder;
use Apple\ApnPush\Encoder\MessageEncoderInterface;
use Apple\ApnPush\Protocol\Http\Authenticator\AuthenticatorInterface;
use Apple\ApnPush\Protocol\Http\ExceptionFactory\ExceptionFactory;
use Apple\ApnPush\Protocol\Http\ExceptionFactory\ExceptionFactoryInterface;
use Apple\ApnPush\Protocol\Http\Sender\CurlHttpSender;
use Apple\ApnPush\Protocol\Http\Sender\HttpSenderInterface;
use Apple\ApnPush\Protocol\Http\UriFactory\UriFactory;
use Apple\ApnPush\Protocol\Http\UriFactory\UriFactoryInterface;
use Apple\ApnPush\Protocol\Http\Visitor\AddApnIdHeaderVisitor;
use Apple\ApnPush\Protocol\Http\Visitor\AddExpirationHeaderVisitor;
use Apple\ApnPush\Protocol\Http\Visitor\AddPriorityHeaderVisitor;
use Apple\ApnPush\Protocol\Http\Visitor\HttpProtocolChainVisitor;
use Apple\ApnPush\Protocol\Http\Visitor\HttpProtocolVisitorInterface;
use Apple\ApnPush\Protocol\HttpProtocol;
use Apple\ApnPush\Sender\Sender;
use Apple\ApnPush\Sender\SenderInterface;

/**
 * Builder for create sender with HTTP 2.0 protocol
 */
class Http20Builder implements BuilderInterface
{
    /**
     * @var \SplPriorityQueue|HttpProtocolVisitorInterface[]
     */
    private $visitors;

    /**
     * @var UriFactoryInterface
     */
    private $uriFactory;

    /**
     * @var MessageEncoderInterface
     */
    private $messageEncoder;

    /**
     * @var AuthenticatorInterface
     */
    private $authenticator;

    /**
     * @var HttpSenderInterface
     */
    private $httpSender;

    /**
     * @var ExceptionFactoryInterface
     */
    private $exceptionFactory;

    /**
     * Constructor.
     *
     * @param AuthenticatorInterface $authenticator
     */
    public function __construct(AuthenticatorInterface $authenticator)
    {
        $this->authenticator = $authenticator;
        $this->visitors = new \SplPriorityQueue();
        $this->uriFactory = new UriFactory();
        $this->messageEncoder = new MessageEncoder();
        $this->httpSender = new CurlHttpSender();
        $this->exceptionFactory = new ExceptionFactory();
    }

    /**
     * Set authenticator
     *
     * @param AuthenticatorInterface $authenticator
     *
     * @return Http20Builder
     */
    public function setAuthenticator(AuthenticatorInterface $authenticator) : Http20Builder
    {
        $this->authenticator = $authenticator;

        return $this;
    }

    /**
     * Add visitor
     *
     * @param HttpProtocolVisitorInterface $visitor
     * @param int                          $priority
     *
     * @return Http20Builder
     */
    public function addVisitor(HttpProtocolVisitorInterface $visitor, int $priority = 0) : Http20Builder
    {
        $this->visitors->insert($visitor, $priority);

        return $this;
    }

    /**
     * Add default visitors
     *
     * @return Http20Builder
     */
    public function addDefaultVisitors() : Http20Builder
    {
        $this->addVisitor(new AddExpirationHeaderVisitor());
        $this->addVisitor(new AddPriorityHeaderVisitor());
        $this->addVisitor(new AddApnIdHeaderVisitor());

        return $this;
    }

    /**
     * Set URI factory
     *
     * @param UriFactoryInterface $uriFactory
     *
     * @return Http20Builder
     */
    public function setUriFactory(UriFactoryInterface $uriFactory) : Http20Builder
    {
        $this->uriFactory = $uriFactory;

        return $this;
    }

    /**
     * Set message encoder
     *
     * @param MessageEncoderInterface $messageEncoder
     *
     * @return Http20Builder
     */
    public function setMessageEncoder(MessageEncoderInterface $messageEncoder) : Http20Builder
    {
        $this->messageEncoder = $messageEncoder;

        return $this;
    }

    /**
     * Set http sender
     *
     * @param HttpSenderInterface $httpSender
     *
     * @return Http20Builder
     */
    public function setHttpSender(HttpSenderInterface $httpSender) : Http20Builder
    {
        $this->httpSender = $httpSender;

        return $this;
    }

    /**
     * Set exception factory
     *
     * @param ExceptionFactoryInterface $exceptionFactory
     *
     * @return Http20Builder
     */
    public function setExceptionFactory(ExceptionFactoryInterface $exceptionFactory) : Http20Builder
    {
        $this->exceptionFactory = $exceptionFactory;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function build() : SenderInterface
    {
        $chainVisitor = $this->createChainVisitor();

        $protocol = new HttpProtocol(
            $this->authenticator,
            $this->httpSender,
            $this->messageEncoder,
            $this->uriFactory,
            $chainVisitor,
            $this->exceptionFactory
        );

        return new Sender($protocol);
    }

    /**
     * Create chain visitor
     *
     * @return HttpProtocolChainVisitor
     */
    private function createChainVisitor() : HttpProtocolChainVisitor
    {
        $chainVisitors = new HttpProtocolChainVisitor();
        $visitors = clone $this->visitors;
        $priority = 0;

        foreach ($visitors as $visitor) {
            $chainVisitors->add($visitor, $priority++);
        }

        return $chainVisitors;
    }
}