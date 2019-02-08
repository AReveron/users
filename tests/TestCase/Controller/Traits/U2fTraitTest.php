<?php
namespace CakeDC\Users\Test\TestCase\Controller\Traits;

use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\ORM\TableRegistry;
use u2flib_server\RegisterRequest;
use u2flib_server\Registration;
use u2flib_server\U2F;

/**
 * Class U2fTraitTest
 *
 * @package App\Test\TestCase\Controller\Traits
 */
class U2fTraitTest extends BaseTraitTest
{
    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'plugin.CakeDC/Users.users',
        'plugin.CakeDC/Users.u2f_registrations',
    ];
    /**
     * setup
     *
     * @return void
     */
    public function setUp()
    {
        $this->traitClassName = 'CakeDC\Users\Controller\Traits\U2fTrait';
        $this->traitMockMethods = ['dispatchEvent', 'isStopped', 'redirect', 'getUsersTable', 'set', 'createU2fLib', 'getData'];

        parent::setUp();

        $this->Trait->Auth = $this->getMockBuilder('Cake\Controller\Component\AuthComponent')
            ->setMethods(['setConfig', 'redirectUrl', 'setUser'])
            ->disableOriginalConstructor()
            ->getMock();

        $request = new ServerRequest();
        $this->Trait->request = $request;
        Configure::write('U2f.enabled', true);
    }

    /**
     * Mock session and mock session attributes
     *
     * @return \Cake\Http\Session
     */
    protected function _mockSession($attributes)
    {
        $session = new \Cake\Http\Session();

        foreach ($attributes as $field => $value) {
            $session->write($field, $value);
        }

        $this->Trait->request
            ->expects($this->any())
            ->method('getSession')
            ->willReturn($session);

        return $session;
    }

    /**
     * Data provider for testU2User
     *
     * @return array
     */
    public function dataProviderU2User()
    {
        $empty = [];
        $withRegistration = [
            'id' => '00000000-0000-0000-0000-000000000001',
            'username' => 'user-1',
        ];
        $withWhoutRegistration = [
            'id' => '00000000-0000-0000-0000-000000000002',
            'username' => 'user-2',
        ];

        return [
            [$empty, ['action' => 'login']],
            [$withWhoutRegistration, ['action' => 'u2fRegister']],
            [$withRegistration, ['action' => 'u2fAuthenticate']]
        ];
    }
    /**
     * Test u2f method
     *
     * @param array $userData session user data
     * @param mixed $redirect expetected redirect
     *
     * @dataProvider dataProviderU2User
     * @return void
     */
    public function testU2fCustomUser($userData, $redirect)
    {
        $this->Trait->request = $this->getMockBuilder('Cake\Http\ServerRequest')
            ->setMethods(['getSession'])
            ->getMock();
        $response = new Response([
            'body' => time()
        ]);
        $this->Trait->expects($this->once())
            ->method('redirect')
            ->with(
                $this->equalTo($redirect)
            )->will($this->returnValue($response));
        $this->_mockSession([
            'U2f.User' => $userData,
        ]);
        $actual = $this->Trait->u2f();
        $this->assertSame($response, $actual);
    }

    /**
     * Test u2fRegister method
     *
     * @return void
     */
    public function testU2fRegisterOkay()
    {
        $this->Trait->request = $this->getMockBuilder('Cake\Http\ServerRequest')
            ->setMethods(['getSession'])
            ->getMock();

        $u2fLib = $this->getMockBuilder(U2F::class)
            ->setConstructorArgs(['https://localhost'])
            ->setMethods(['getRegisterData'])
            ->getMock();

        $registerRequest = new RegisterRequest("sample chalange", "https://localhost");
        $signs = [
            ['fake' => new \stdClass()],
            ['fake2' => new \stdClass()],
        ];
        $u2fLib->expects($this->once())
            ->method('getRegisterData')
            ->will($this->returnValue([$registerRequest, $signs]));

        $this->Trait->expects($this->once())
            ->method('createU2fLib')
            ->will($this->returnValue($u2fLib));
        $this->Trait->expects($this->once())
            ->method('set')
            ->with(
                $this->equalTo([
                'registerRequest' => $registerRequest,
                'signs' => $signs
                ])
            );
        $this->Trait->expects($this->never())
            ->method('redirect');

        $this->_mockSession([
            'U2f.User' => [
                'id' => '00000000-0000-0000-0000-000000000002',
                'username' => 'user-2',
            ],
        ]);
        $actual = $this->Trait->u2fRegister();
        $this->assertNull($actual);
        $actual = $this->Trait->request->getSession()->read('U2f.registerRequest');
        $expected = json_encode($registerRequest);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Data provider for testU2fRegisterRedirect
     *
     * @return array
     */
    public function dataProviderU2fRegisterRedirect()
    {
        $empty = [];
        $withRegistration = [
            'id' => '00000000-0000-0000-0000-000000000001',
            'username' => 'user-1',
        ];

        return [
            [$empty, ['action' => 'login']],
            [$withRegistration, ['action' => 'u2fAuthenticate']]
        ];
    }

    /**
     * Test u2fRegister method
     *
     * @param array $userData session user data
     * @param mixed $redirect expetected redirect
     *
     * @dataProvider dataProviderU2fRegisterRedirect
     * @return void
     */
    public function testU2fRegisterRedirect($userData, $redirect)
    {
        $this->Trait->request = $this->getMockBuilder('Cake\Http\ServerRequest')
            ->setMethods(['getSession'])
            ->getMock();

        $this->Trait->expects($this->never())
            ->method('createU2fLib');

        $this->Trait->expects($this->never())
            ->method('set');

        $this->_mockSession([
            'U2f.User' => $userData,
        ]);
        $response = new Response([
            'body' => time()
        ]);
        $this->Trait->expects($this->once())
            ->method('redirect')
            ->with(
                $this->equalTo($redirect)
            )->will($this->returnValue($response));

        $actual = $this->Trait->u2fRegister();
        $this->assertSame($response, $actual);
        $actual = $this->Trait->request->getSession()->read('U2f.registerRequest');
        $expected = null;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test u2fRegister method
     *
     * @return void
     */
    public function testU2fRegisterFinish()
    {
        $this->Trait->request = $this->getMockBuilder('Cake\Http\ServerRequest')
            ->setMethods(['getSession', 'getData'])
            ->getMock();

        $u2fLib = $this->getMockBuilder(U2F::class)
            ->setConstructorArgs(['https://localhost'])
            ->setMethods(['doRegister'])
            ->getMock();

        $registerRequest = new RegisterRequest("sample chalange", "https://localhost");
        $registerRequest = json_decode(json_encode($registerRequest));
        $signs = [
            ['fake' => new \stdClass()],
            ['fake2' => new \stdClass()],
        ];
        $registerResponse = json_decode(json_encode([
            'fakeA' => 'fakevaluea',
            'fakeB' => 'fakevalueb'
        ]));
        $registration = new Registration();
        $registration->certificate = "user registration cert " . time();
        $registration->counter = 1;
        $registration->publicKey = "pub skska08u90234230990";
        $registration->keyHandle = 'hahdofa02390423udu9ma0dumfá0dsufm2um9432uu903u923';

        $this->Trait->request->expects($this->once())
            ->method('getData')
            ->with($this->equalTo('registerResponse'))
            ->will($this->returnValue(json_encode($registerResponse)));
        $this->_mockSession([
            'U2f' => [
                'User' => [
                    'id' => '00000000-0000-0000-0000-000000000002',
                    'username' => 'user-2',
                ],
                'registerRequest' => json_encode($registerRequest)
            ],
        ]);
        $u2fLib->expects($this->once())
            ->method('doRegister')
            ->with(
                $this->equalTo($registerRequest),
                $this->equalTo($registerResponse)
            )
            ->will($this->returnValue($registration));

        $this->Trait->expects($this->once())
            ->method('createU2fLib')
            ->will($this->returnValue($u2fLib));

        $actual = $this->Trait->request->getSession()->read('U2f');
        $this->assertNotNull($actual);

        $response = new Response();
        $this->Trait->expects($this->once())
            ->method('redirect')
            ->with(
                $this->equalTo([
                    'action' => 'u2fAuthenticate'
                ])
            )->will($this->returnValue($response));

        $actual = $this->Trait->u2fRegisterFinish();
        $this->assertSame($response, $actual);
        $actual = $this->Trait->request->getSession()->read('U2f');
        $this->assertEquals(
            [
                'User' => [
                    'id' => '00000000-0000-0000-0000-000000000002',
                    'username' => 'user-2',
                ],
            ],
            $actual
        );

        $registration = json_decode(json_encode($registration), true);
        $registration['user_id'] = '00000000-0000-0000-0000-000000000002';
        $exists = TableRegistry::getTableLocator()->get('CakeDC/Users.U2fRegistrations')->exists($registration);
        $this->assertTrue($exists);

        $registration = new Registration();
        $registration->certificate = "user registration cert " . time();
        $registration->counter = 1;
        $registration->publicKey = "pub skska08u90234230990";
        $registration->keyHandle = 'hahdofa02390423udu9ma0dumfá0dsufm2um9432uu903u923';
    }

    /**
     * Test u2fRegister method
     *
     * @return void
     */
    public function testU2fRegisterFinishException()
    {
        $this->Trait->request = $this->getMockBuilder('Cake\Http\ServerRequest')
            ->setMethods(['getSession', 'getData'])
            ->getMock();

        $u2fLib = $this->getMockBuilder(U2F::class)
            ->setConstructorArgs(['https://localhost'])
            ->setMethods(['doRegister'])
            ->getMock();

        $registerRequest = new RegisterRequest("sample chalange", "https://localhost");
        $registerRequest = json_decode(json_encode($registerRequest));
        $registerResponse = json_decode(json_encode([
            'fakeA' => 'fakevaluea',
            'fakeB' => 'fakevalueb'
        ]));
        $registration = new Registration();
        $registration->certificate = "user registration cert " . time();
        $registration->counter = 1;
        $registration->publicKey = "pub skska08u90234230990";
        $registration->keyHandle = 'hahdofa02390423udu9ma0dumfá0dsufm2um9432uu903u923';

        $this->Trait->request->expects($this->once())
            ->method('getData')
            ->with($this->equalTo('registerResponse'))
            ->will($this->returnValue(json_encode($registerResponse)));
        $this->_mockSession([
            'U2f' => [
                'User' => [
                    'id' => '00000000-0000-0000-0000-000000000002',
                    'username' => 'user-2',
                ],
                'registerRequest' => json_encode($registerRequest)
            ],
        ]);
        $u2fLib->expects($this->once())
            ->method('doRegister')
            ->with(
                $this->equalTo($registerRequest),
                $this->equalTo($registerResponse)
            )
            ->will($this->throwException(new \Exception('Invalid request')));

        $this->Trait->expects($this->once())
            ->method('createU2fLib')
            ->will($this->returnValue($u2fLib));

        $actual = $this->Trait->request->getSession()->read('U2f');
        $this->assertNotNull($actual);

        $response = new Response();
        $this->Trait->expects($this->once())
            ->method('redirect')
            ->with(
                $this->equalTo([
                    'action' => 'u2fRegister'
                ])
            )->will($this->returnValue($response));

        $actual = $this->Trait->u2fRegisterFinish();
        $this->assertSame($response, $actual);
        $actual = $this->Trait->request->getSession()->read('U2f');
        $this->assertEquals(
            [
                'User' => [
                    'id' => '00000000-0000-0000-0000-000000000002',
                    'username' => 'user-2',
                ],
            ],
            $actual
        );

        $registration = json_decode(json_encode($registration), true);
        $registration['user_id'] = '00000000-0000-0000-0000-000000000002';
        $exists = TableRegistry::getTableLocator()->get('CakeDC/Users.U2fRegistrations')->exists($registration);
        $this->assertFalse($exists);

        $registration = new Registration();
        $registration->certificate = "user registration cert " . time();
        $registration->counter = 1;
        $registration->publicKey = "pub skska08u90234230990";
        $registration->keyHandle = 'hahdofa02390423udu9ma0dumfá0dsufm2um9432uu903u923';
    }

    /**
     * Data provider for testU2fAuthenticateRedirectCustomUser
     *
     * @return array
     */
    public function dataProviderU2fAuthenticateRedirectCustomUser()
    {
        $empty = [];
        $withWhoutRegistration = [
            'id' => '00000000-0000-0000-0000-000000000002',
            'username' => 'user-2',
        ];

        return [
            [$empty, ['action' => 'login']],
            [$withWhoutRegistration, ['action' => 'u2fRegister']],
        ];
    }
    /**
     * Test u2fAuthenticate method redirect cases
     *
     * @param array $userData session user data
     * @param mixed $redirect expetected redirect
     *
     * @dataProvider dataProviderU2fAuthenticateRedirectCustomUser
     * @return void
     */
    public function testU2fAuthenticateRedirectCustomUser($userData, $redirect)
    {
        $this->Trait->request = $this->getMockBuilder('Cake\Http\ServerRequest')
            ->setMethods(['getSession'])
            ->getMock();
        $response = new Response([
            'body' => time()
        ]);
        $this->Trait->expects($this->once())
            ->method('redirect')
            ->with(
                $this->equalTo($redirect)
            )->will($this->returnValue($response));
        $this->_mockSession([
            'U2f.User' => $userData,
        ]);
        $actual = $this->Trait->u2fAuthenticate();
        $this->assertSame($response, $actual);
    }

    /**
     * Test u2fAuthenticate method
     *
     * @return void
     */
    public function testU2fAuthenticate()
    {
        $this->Trait->request = $this->getMockBuilder('Cake\Http\ServerRequest')
            ->setMethods(['getSession'])
            ->getMock();

        $u2fLib = $this->getMockBuilder(U2F::class)
            ->setConstructorArgs(['https://localhost'])
            ->setMethods(['getAuthenticateData'])
            ->getMock();

        $signs = [
            ['fake' => new \stdClass()],
            ['fake2' => new \stdClass()],
        ];
        $registrations = TableRegistry::getTableLocator()
            ->get('CakeDC/Users.U2fRegistrations')
            ->findByUserId('00000000-0000-0000-0000-000000000001')
            ->all()
            ->toArray();
        $this->assertCount(1, $registrations);
        $u2fLib->expects($this->once())
            ->method('getAuthenticateData')
            ->with(
                $this->equalTo($registrations)
            )
            ->will($this->returnValue($signs));

        $this->Trait->expects($this->once())
            ->method('createU2fLib')
            ->will($this->returnValue($u2fLib));
        $this->Trait->expects($this->once())
            ->method('set')
            ->with(
                $this->equalTo([
                    'authenticateRequest' => $signs
                ])
            );
        $this->Trait->expects($this->never())
            ->method('redirect');

        $this->_mockSession([
            'U2f.User' => [
                'id' => '00000000-0000-0000-0000-000000000001',
                'username' => 'user-1',
            ],
        ]);
        $actual = $this->Trait->u2fAuthenticate();
        $this->assertNull($actual);
        $actual = $this->Trait->request->getSession()->read('U2f.authenticateRequest');
        $expected = json_encode($signs);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test u2fAuthenticateFinish method
     *
     * @return void
     */
    public function testU2fAutheticateFinish()
    {
        $registration = TableRegistry::getTableLocator()
            ->get('CakeDC/Users.U2fRegistrations')
            ->findByUserId('00000000-0000-0000-0000-000000000001')
            ->first();
        $this->assertNotNull($registration);

        $registrationEntityResult = new Registration();
        $registrationEntityResult->keyHandle = $registration->keyHandle;
        $registrationEntityResult->publicKey = $registration->publicKey;
        $registrationEntityResult->counter = $registration->counter + 1;
        $registrationEntityResult->certificate = $registration->certificate;

        $this->Trait->Auth->expects($this->once())
            ->method('redirectUrl')
            ->will($this->returnValue('/my-home-page'));

        $this->Trait->Auth->expects($this->once())
            ->method('setUser')
            ->with(
                $this->equalTo([
                    'id' => '00000000-0000-0000-0000-000000000001',
                    'username' => 'user-1',
                ])
            );

        $this->Trait->request = $this->getMockBuilder('Cake\Http\ServerRequest')
            ->setMethods(['getSession', 'getData'])
            ->getMock();

        $u2fLib = $this->getMockBuilder(U2F::class)
            ->setConstructorArgs(['https://localhost'])
            ->setMethods(['doAuthenticate'])
            ->getMock();

        $signs = json_decode(json_encode([
            ['fake' => new \stdClass()],
            ['fake2' => new \stdClass()],
        ]));
        $authenticateResponse = json_decode(json_encode([
            'fakeA' => 'fakevaluea',
            'fakeB' => 'fakevalueb'
        ]));

        $this->Trait->request->expects($this->once())
            ->method('getData')
            ->with($this->equalTo('authenticateResponse'))
            ->will($this->returnValue(json_encode($authenticateResponse)));
        $this->_mockSession([
            'U2f' => [
                'User' => [
                    'id' => '00000000-0000-0000-0000-000000000001',
                    'username' => 'user-1',
                ],
                'authenticateRequest' => json_encode($signs)
            ],
        ]);

        $u2fLib->expects($this->once())
            ->method('doAuthenticate')
            ->with(
                $this->equalTo($signs),
                $this->equalTo([$registration]),
                $this->equalTo($authenticateResponse)
            )
            ->will($this->returnValue($registrationEntityResult));

        $this->Trait->expects($this->once())
            ->method('createU2fLib')
            ->will($this->returnValue($u2fLib));

        $actual = $this->Trait->request->getSession()->read('U2f');
        $this->assertNotNull($actual);

        $response = new Response();
        $this->Trait->expects($this->once())
            ->method('redirect')
            ->with('/my-home-page')->will($this->returnValue($response));

        $actual = $this->Trait->u2fAuthenticateFinish();
        $this->assertSame($response, $actual);
        $actual = $this->Trait->request->getSession()->read('U2f');
        $this->assertNull($actual);

        $updatedEntity = TableRegistry::getTableLocator()->get('CakeDC/Users.U2fRegistrations')->get($registration['id']);
        $this->assertEquals($registrationEntityResult->counter, $updatedEntity->counter);
    }

    /**
     * Test u2fAuthenticateFinish method with exception
     *
     * @return void
     */
    public function testU2fAutheticateFinishWithException()
    {
        $registration = TableRegistry::getTableLocator()
            ->get('CakeDC/Users.U2fRegistrations')
            ->findByUserId('00000000-0000-0000-0000-000000000001')
            ->first();
        $counter = $registration->counter;
        $this->assertNotNull($registration);

        $this->Trait->Auth->expects($this->never())
            ->method('redirectUrl');

        $this->Trait->Auth->expects($this->never())
            ->method('setUser');

        $this->Trait->request = $this->getMockBuilder('Cake\Http\ServerRequest')
            ->setMethods(['getSession', 'getData'])
            ->getMock();

        $u2fLib = $this->getMockBuilder(U2F::class)
            ->setConstructorArgs(['https://localhost'])
            ->setMethods(['doAuthenticate'])
            ->getMock();

        $signs = json_decode(json_encode([
            ['fake' => new \stdClass()],
            ['fake2' => new \stdClass()],
        ]));
        $authenticateResponse = json_decode(json_encode([
            'fakeA' => 'fakevaluea',
            'fakeB' => 'fakevalueb'
        ]));

        $this->Trait->request->expects($this->once())
            ->method('getData')
            ->with($this->equalTo('authenticateResponse'))
            ->will($this->returnValue(json_encode($authenticateResponse)));

        $this->_mockSession([
            'U2f' => [
                'User' => [
                    'id' => '00000000-0000-0000-0000-000000000001',
                    'username' => 'user-1',
                ],
                'authenticateRequest' => json_encode($signs)
            ],
        ]);

        $u2fLib->expects($this->once())
            ->method('doAuthenticate')
            ->with(
                $this->equalTo($signs),
                $this->equalTo([$registration]),
                $this->equalTo($authenticateResponse)
            )
            ->will($this->throwException(new \Exception('Invalid')));

        $this->Trait->expects($this->once())
            ->method('createU2fLib')
            ->will($this->returnValue($u2fLib));

        $response = new Response();
        $this->Trait->expects($this->once())
            ->method('redirect')
            ->with(['action' => 'u2fAuthenticate'])
            ->will($this->returnValue($response));

        $actual = $this->Trait->u2fAuthenticateFinish();
        $this->assertSame($response, $actual);
        $actual = $this->Trait->request->getSession()->read('U2f');
        $this->assertEquals(
            [
                'User' => [
                    'id' => '00000000-0000-0000-0000-000000000001',
                    'username' => 'user-1',
                ],
            ],
            $actual
        );

        $updatedEntity = TableRegistry::getTableLocator()->get('CakeDC/Users.U2fRegistrations')->get($registration['id']);
        $this->assertEquals($counter, $updatedEntity->counter);
    }
}
