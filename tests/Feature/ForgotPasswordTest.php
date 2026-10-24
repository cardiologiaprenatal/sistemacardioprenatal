<?php

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

test('login exibe esqueci minha senha sem oferta de e-mail', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Esqueci minha senha')
        ->assertSee('esqueci-senha', false)
        ->assertDontSee('Receber link por e-mail')
        ->assertDontSee('esqueci-senha/email', false);
});

test('tela de recuperacao e somente whatsapp', function () {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertSee('Telefone com DDD')
        ->assertSee('Enviar código')
        ->assertSee('Voltar para o login')
        ->assertDontSee('Receber link por e-mail')
        ->assertDontSee('Recuperar por e-mail')
        ->assertDontSee('E-mail cadastrado')
        ->assertDontSee('esqueci-senha/email', false);
});

test('rotas antigas de recuperacao por e-mail nao ficam acessiveis', function () {
    $this->get('/esqueci-senha/email')->assertNotFound();
    $this->post('/esqueci-senha', ['email' => 'pessoa@example.com'])->assertMethodNotAllowed();
    $this->get('/redefinir-senha/token-antigo')->assertNotFound();
    $this->post('/redefinir-senha', [
        'token' => 'token-antigo',
        'email' => 'pessoa@example.com',
        'password' => 'nova-senha-456',
        'password_confirmation' => 'nova-senha-456',
    ])->assertNotFound();
});
