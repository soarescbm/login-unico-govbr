<?php

namespace Soarescbm\OAuth2\Client\Provider;

use Soarescbm\OAuth2\Client\Provider\Exception\GovBrIdentityProviderException;
use Soarescbm\Oauth2\Client\Provider\Exception\GovBrInvalidArgumentException;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

class GovBr extends AbstractProvider
{

    use BearerAuthorizationTrait;

    /** 
     * @var bool 
     */
    protected $env_production = true;

    /**
     * @var string
     */
    protected $domain_production = 'https://sso.acesso.gov.br';
    protected $domain_staging = 'https://sso.staging.acesso.gov.br';
    protected $domain_api_production = 'https://api.acesso.gov.br';
    protected $domain_api_staging = 'https://api.staging.acesso.gov.br';
    protected $urlAuthorize;
    protected $urlAccessToken;
    protected $urlResourceOwnerDetails;
    protected $urlLogout;
    protected $redirectUriLogout;


    /**
     * @param array $options
     * @param array $collaborators
     */
    public function __construct(array $options = [], array $collaborators = [])
    {
        $this->assertRequiredOptions($options);
        $options = $this->getConfigurableOptions($options);
        parent::__construct($options, $collaborators);
    }

    /**
     * @inheritdoc
     */
    public function getBaseAuthorizationUrl()
    {
        return $this->urlAuthorize;
    }

    /**
     * @inheritdoc
     */
    public function getBaseAccessTokenUrl(array $params)
    {
        return $this->urlAccessToken;
    }

    /**
     * @inheritdoc
     */
    public function getResourceOwnerDetailsUrl(AccessToken $token)
    {
        return $this->urlResourceOwnerDetails;
    }

    /**
     * @inheritdoc
     */
    protected function getAuthorizationParameters(array $options)
    {
        if (!isset($options['nonce'])) {
            $options['nonce'] = md5(uniqid('govbr', true));
        }

        return parent::getAuthorizationParameters($options);
    }

    /**
     * @inheritdoc
     */
    public function getScopeSeparator()
    {
        return ' ';
    }

    /**
     * @inheritdoc
     * @see https://manual-roteiro-integracao-login-unico.servicos.gov.br/pt/stable/escopoatributos.html
     */
    protected function getDefaultScopes()
    {
        return [
            'openid',
            'email',
            'phone',
            'profile',
            'govbr_confiabilidades',
            'govbr_empresa'
        ];
    }


    /**
     * @inheritDoc
     * @see https://manual-roteiro-integracao-login-unico.servicos.gov.br/pt/stable/iniciarintegracao.html#passo-9
     */
    protected function createResourceOwner(array $response, AccessToken $token)
    {
        return new GovBrResourceOwner($response, $token);
    }

    /**
     * Verificação de resposta do gov.br
     * 
     * @param ResponseInterface $response
     * @param array|string $data
     * @throws GovBrIdentityProviderException
     * @see https://manual-roteiro-integracao-login-unico.servicos.gov.br/pt/stable/iniciarintegracao.html#resultados-esperados-ou-erros-do-acesso-ao-servicos-do-login-unico
     */

    protected function checkResponse(ResponseInterface $response, $data)
    {

        if ($response->getStatusCode() >= 400 || !empty($data['error'])) {

            $message = isset($data['erro_description']) ? $data['erro_description'] : $response->getReasonPhrase();
            $code = isset($data['error']) ? (int) isset($data['error']) : $response->getStatusCode();
            throw new GovBrIdentityProviderException($message, $code,  (string) $response->getBody());
        }
    }


    /**
     * Configuração do ambiente homologacao (staging) ou produção (production) conforme o parâmetro (clientEnv) passado, o ambiente padrao é o production (produção).
     * 
     * @param array $options
     * @return array
     */

    protected function  getConfigurableOptions(array $options)
    {
        $domain = $this->domain_production;

        if (key_exists('clientEnv', $options)) {
            if ($options['clientEnv'] === 'staging') {
                $this->env_production = false;
                $domain = $this->domain_staging;
                unset($options['clientEnv']);
            }
        }

        $this->urlAuthorize = $domain . '/authorize';
        $this->urlAccessToken = $domain . '/token';
        $this->urlResourceOwnerDetails = $domain . '/userinfo';
        $this->urlLogout = $domain . '/logout';


        return $options;
    }

    /**
     * Parâmetros requeridos
     * 
     * @return array
     */
    protected function getRequiredOptions()
    {
        return [
            'clientId',
            'clientSecret',
            'redirectUri',
            'redirectUriLogout',
        ];
    }

    /**
     * Verifica se todos os parâmetros requeridos foram passados
     *
     * @param  array $options
     * @return void
     * @throws GovBrInvalidArgumentException
     */
    private function assertRequiredOptions(array $options)
    {
        $missing = array_diff_key(array_flip($this->getRequiredOptions()), $options);

        if (!empty($missing)) {
            throw new GovBrInvalidArgumentException (
                'Parâmento requerido não definido: ' . implode(', ', array_keys($missing))
            );
        }
    }

     /**
     * Retorna a url de Log Out do usuário autenticado no Gov.br.
     * O acesso ao Log Out deverá ser pelo Front End da aplicação a ser integrada com Login Único,
     * por meio do método GET ou POST.
     * 
     * @return string
     * @see https://manual-roteiro-integracao-login-unico.servicos.gov.br/pt/stable/iniciarintegracao.html#acesso-ao-servico-de-log-out
     */
    public function getLogoutUrl()
    {
        $query  = $this->buildQueryString(['post_logout_redirect_uri' => urlencode($this->redirectUriLogout)]);
        return $this->appendQuery($this->urlLogout, $query);
    }

   

    /**
     * Retorna as pessoas júridicas vinculadas ao cpf do usuário autenticado no Gov.br.
     * 
     * @param GovBrResourceOwner $govBrUser
     * @return array
     * @see https://manual-roteiro-integracao-login-unico.servicos.gov.br/pt/stable/iniciarintegracao.html#acesso-ao-servico-de-cadastro-de-pessoas-juridicas
     */
    public function getCompanies(GovBrResourceOwner $govBrUser)
    {
        $request = $this->getAuthenticatedRequest(
            self::METHOD_GET,
            $this->getUrlCompanies($govBrUser->getCpf()),
            $govBrUser->token()
        );

        $response = $this->getResponse($request);
        $content = $this->parseJson($response->getBody());

        return $content;
    }

    /**
     * Retorna a url de consulta de pessoas jurídicas vinculadas ao cpf do usuário autenticado no gov.br.
     * 
     * @param string $cpf
     * @return string
     */
    private function getUrlCompanies(string $cpf)
    {
        
        if (empty($cpf)) {
            throw new GovBrInvalidArgumentException('Cpf inválido para consulta de empresas vinculadas ao mesmo');
        }
        $domain_api =  $this->domain_api_production;

        if ($this->env_production === false) {
            $domain_api = $this->domain_api_staging;
        }

        $query  = $this->buildQueryString(['filtrar-por-participante' => $cpf]);
        $url = $domain_api . '/empresas/v2/empresas';
        
        return $this->appendQuery($url, $query);
    }
}
