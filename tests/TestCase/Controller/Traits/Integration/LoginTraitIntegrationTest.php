<?php
declare(strict_types=1);

/**
 * Copyright 2010 - 2019, Cake Development Corporation (https://www.cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2010 - 2018, Cake Development Corporation (https://www.cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

namespace CakeDC\Users\Test\TestCase\Controller\Traits\Integration;


use Cake\Core\Configure;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class LoginTraitIntegrationTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'plugin.CakeDC/Users.Users',
    ];

    /**
     * Test login action with get request
     *
     * @return void
     */
    public function testLoginGetRequest()
    {
        $this->get('/login');
        $this->assertResponseOk();
        $this->assertResponseNotContains('Username or password is incorrect');
        $this->assertResponseContains('<form method="post" accept-charset="utf-8" action="/login">');
        $this->assertResponseContains('<legend>Please enter your username and password</legend>');
        $this->assertResponseContains('<input type="text" name="username" required="required" id="username"/>');
        $this->assertResponseContains('<input type="password" name="password" required="required" id="password"/>');
        $this->assertResponseContains('<input type="checkbox" name="remember_me" value="1" checked="checked" id="remember-me">');
        $this->assertResponseContains('<button type="submit">Login</button>');
        $this->assertResponseContains('<a href="/register">Register</a>');
        $this->assertResponseContains('<a href="/users/request-reset-password">Reset Password</a>');
    }

    /**
     * Test login action with get request
     *
     * @return void
     */
    public function testLoginPostRequestInvalidPassword()
    {
        $this->post('/login', [
            'username' => 'user-2',
            'password' => '123456789'
        ]);
        $this->assertResponseOk();
        $this->assertResponseContains('Username or password is incorrect');
        $this->assertResponseContains('<form method="post" accept-charset="utf-8" action="/login">');
        $this->assertResponseContains('<legend>Please enter your username and password</legend>');
        $this->assertResponseContains('<input type="text" name="username" required="required" id="username" value="user-2"/>');
        $this->assertResponseContains('<input type="password" name="password" required="required" id="password" value="123456789"/>');
        $this->assertResponseContains('<input type="checkbox" name="remember_me" value="1" checked="checked" id="remember-me">');
        $this->assertResponseContains('<button type="submit">Login</button>');
    }

    /**
     * Test login action with get request
     *
     * @return void
     */
    public function testLoginPostRequestRightPassword()
    {
        $this->enableRetainFlashMessages();
        $this->post('/login', [
            'username' => 'user-2',
            'password' => '12345'
        ]);
        $this->assertRedirect('/pages/home');
    }
}