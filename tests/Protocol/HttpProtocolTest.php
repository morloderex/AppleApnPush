<?php

/*
 * This file is part of the AppleApnPush package
 *
 * (c) Vitaliy Zhuk <zhuk2205@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Tests\Apple\ApnPush\Protocol;

use Apple\ApnPush\Encoder\PayloadEncoderInterface;
use Apple\ApnPush\Exception\SendNotification\SendNotificationException;
use Apple\ApnPush\Model\Alert;
use Apple\ApnPush\Model\Aps;
use Apple\ApnPush\Model\DeviceToken;
use Apple\ApnPush\Model\Notification;
use Apple\ApnPush\Model\Payload;
use Apple\ApnPush\Model\Receiver;
use Apple\ApnPush\Protocol\Http\Authenticator\AuthenticatorInterface;
use Apple\ApnPush\Protocol\Http\ExceptionFactory\ExceptionFactoryInterface;
use Apple\ApnPush\Protocol\Http\Request;
use Apple\ApnPush\Protocol\Http\Response;
use Apple\ApnPush\Protocol\Http\Sender\HttpSenderInterface;
use Apple\ApnPush\Protocol\Http\UriFactory\UriFactoryInterface;
use Apple\ApnPush\Protocol\Http\Visitor\HttpProtocolVisitorInterface;
use Apple\ApnPush\Protocol\HttpProtocol;
use PHPUnit\Framework\TestCase;

class HttpProtocolTest extends TestCase
{
    /**
     * @var AuthenticatorInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $authenticator;

    /**
     * @var HttpSenderInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $httpSender;

    /**
     * @var PayloadEncoderInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $payloadEncoder;

    /**
     * @var UriFactoryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $uriFactory;

    /**
     * @var HttpProtocolVisitorInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $visitor;

    /**
     * @var ExceptionFactoryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $exceptionFactory;

    /**
     * @var HttpProtocol
     */
    private $protocol;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->authenticator = $this->createMock(AuthenticatorInterface::class);
        $this->httpSender = $this->createMock(HttpSenderInterface::class);
        $this->payloadEncoder = $this->createMock(PayloadEncoderInterface::class);
        $this->uriFactory = $this->createMock(UriFactoryInterface::class);
        $this->visitor = $this->createMock(HttpProtocolVisitorInterface::class);
        $this->exceptionFactory = $this->createMock(ExceptionFactoryInterface::class);

        $this->protocol = new HttpProtocol(
            $this->authenticator,
            $this->httpSender,
            $this->payloadEncoder,
            $this->uriFactory,
            $this->visitor,
            $this->exceptionFactory
        );
    }

    /**
     * @test
     */
    public function shouldSuccessSend()
    {
        $deviceToken = new DeviceToken(str_repeat('af', 32));
        $receiver = new Receiver($deviceToken, 'com.test');
        $payload = new Payload(new Aps(new Alert()));
        $notification = new Notification($payload);

        $this->payloadEncoder->expects(self::once())
            ->method('encode')
            ->with($payload)
            ->willReturn('{"aps":{}}');

        $this->uriFactory->expects(self::once())
            ->method('create')
            ->with($deviceToken, false)
            ->willReturn('https://some.com/'.$deviceToken);

        $this->authenticator->expects(self::once())
            ->method('authenticate')
            ->with(self::isInstanceOf(Request::class))
            ->willReturnCallback(function (Request $innerRequest) {
                return $innerRequest;
            });

        $this->visitor->expects(self::once())
            ->method('visit')
            ->with($notification, self::isInstanceOf(Request::class));

        $this->httpSender->expects(self::once())
            ->method('send')
            ->with(self::isInstanceOf(Request::class))
            ->willReturn(new Response(200, '{}'));

        $this->protocol->send($receiver, $notification, false);
    }

    /**
     * @test
     *
     * @expectedException \Apple\ApnPush\Exception\SendNotification\SendNotificationException
     */
    public function shouldFailSend()
    {
        $deviceToken = new DeviceToken(str_repeat('af', 32));
        $receiver = new Receiver($deviceToken, 'com.test');
        $payload = new Payload(new Aps(new Alert()));
        $notification = new Notification($payload);

        $this->payloadEncoder->expects(self::once())
            ->method('encode')
            ->with($payload)
            ->willReturn('{"aps":{}}');

        $this->uriFactory->expects(self::once())
            ->method('create')
            ->with($deviceToken, false)
            ->willReturn('https://some.com/'.$deviceToken);

        $this->authenticator->expects(self::once())
            ->method('authenticate')
            ->with(self::isInstanceOf(Request::class))
            ->willReturnCallback(function (Request $innerRequest) {
                return $innerRequest;
            });

        $this->visitor->expects(self::once())
            ->method('visit')
            ->with($notification, self::isInstanceOf(Request::class));

        $this->httpSender->expects(self::once())
            ->method('send')
            ->with(self::isInstanceOf(Request::class))
            ->willReturn(new Response(404, '{}'));

        $this->httpSender->expects(self::once())
            ->method('close');

        $this->exceptionFactory->expects(self::once())
            ->method('create')
            ->with(new Response(404, '{}'))
            ->willReturn($this->createMock(SendNotificationException::class));

        $this->protocol->send($receiver, $notification, false);
    }
}
