<?php

namespace Soarescbm\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;

class GovBrResourceOwner implements ResourceOwnerInterface
{
    /**
     * @var array
     */
    protected $response;

    /**
     * @var AccessToken
     */
    protected $token;

    public function __construct(array $response, AccessToken $token)
    {
        $this->response = $response;
        $this->token = $token;
    }

    /**
     * Retorna o CPF do usuário autenticado no Gov.br.
     * 
     * @return mixed
     */
    public function getId()
    {
        return $this->getResponseValue('sub');
    }

    /**
     * Retorna o CPF do usuário autenticado no Gov.br.
     * 
     * @return mixed
     */
    public function getCpf()
    {
        return $this->getResponseValue('sub');
    }

    /**
     * Retorna o nome cadastrado no Gov.br do usuário autenticado.
     * 
     * @return mixed
     */
    public function getName()
    {
        return $this->getResponseValue('name');
    }

    /**
     * Retorna todo as informações do usuário autenticado no Gov.br.
     * 
     * @return array
     */
    public function toArray()
    {
        $response = $this->response;
        $response['cpf'] = $this->getCpf();
        return $response;
    }

    /**
     * Retorna o email do usuário autenticado no Gov.br.
     * 
     * @return mixed
     */
    public function getEmail()
    {
        return $this->getResponseValue('email');
    }

    /**
     * Retorna a confirmação se o email do usuário autenticado foi verificado pelo Gov.br.
     * 
     * @return bool
     */
    public function emailVerified()
    {
        return (bool) $this->getResponseValue('email_verified');
    }

    /**
     * Retorna o telefone do usuário autenticado no Gov.br.
     * 
     * @return mixed
     */
    public function getPhoneNumber()
    {
        return $this->getResponseValue('phone_number');
    }

    /**
     * Retorna a confirmação  se o telefone do usuário autenticado foi verificado pelo gov.br
     * 
     * @return bool
     */
    public function phoneNumberVerified()
    {
        return (bool) $this->getResponseValue('phone_number_verified');
    }

    /**
     * Retorna a url de acesso à foto do usuário cadastrada no gov.br do usuário autenticado. 
     * A mesma é protegida e pode ser acessada passando o access token recebido.
     * 
     * @return mixed
     */
    public function getAvatarUrl()
    {
        return $this->getResponseValue('picture');
    }

    /**
     * Retorna Listagem dos fatores de autenticação do usuário. Pode ser “app” se logou por QR-CODE do aplicativo gov.br,
     * “passwd” se o mesmo logou fornecendo a senha, “x509” se o mesmo utilizou certificado digital ou certificado em nuvem, 
     * ou “bank” para indicar utilização de conta bancária para autenticar. Esse último seguirá com número de identificação 
     * do banco, conforme código de compensação do Bacen presente ao final da explicação.
     * 
     * @return mixed
     */
    public function getAmr()
    {
        return $this->getResponseValue('amr');
    }

    /**
     * Retorna o CNPJ vinculado ao usuário autenticado. Atributo será preenchido quando autenticação ocorrer por certificado digital de pessoal jurídica.
     * 
     * @return mixed
     */
    public function getCnpj()
    {
        return $this->getResponseValue('cnpj');
    }

    

    /**
     * Retorna o token de acesso do recurso autorizado.
     * 
     * @return AccessToken
     */
    public function token()
    {
        return $this->token;
    }

    /**
     * @param string $key
     * @return mixed
     */
    private function getResponseValue($key)
    {
        if (isset($this->response[$key])) {
            return $this->response[$key];
        } else {
            return null;
        }
    }
}
