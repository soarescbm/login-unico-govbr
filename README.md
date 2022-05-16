# Cliente para Login Único Gov.Br

Esse pacote fornece autenticação Gov.br no padrão OAuth 2.0 suportado por PHP League's [OAuth 2.0 Client](https://github.com/thephpleague/oauth2-client).


## Requisitos

Versões suportadas do PHP:

* PHP 8.0
* PHP 7.4
* PHP 7.3
* PHP 7.2
* PHP 7.1
* PHP 7.0
* PHP 5.6

## Instalação

Para instalar, use o  composer:

```
composer require soarescbm/login-unico-govbr

```

## Uso

O uso é o mesmo do cliente The League's OAuth, usando `\Soarescbm\OAuth2\Client\Provider\GovBr` como provedor.


### Código do Fluxo de Autorização


```php

/**
 *
 * Exemplo do fluxo de autorização login único Gov.br usando dependências com o composer, compatível com o php >= 5.6
 * Nesse exemplo o fluxo usa um servidor apache com php 5.6 através de um container docker
 * Para viabilizar a execução do fluxo, um .htaccess direciona todo o fluxo de requisição para esse arquivo.
 *
 * @author Paulo Soares <soarescbm@gmail.com>
 * 
 */

ini_set('display_errors','On'); 
session_start(); 


require "vendor/autoload.php";

// Parâmentros de configuração para autenticação Gov.br
// Essas informações são específicas para cada aplicação, devendo ser obtidas junto ao Gov.br, conforme roteiro disponível no link abaixo.
// https://manual-roteiro-integracao-login-unico.servicos.gov.br/pt/stable/solicitarconfiguracao.html


 $config = [
    'clientEnv'           => 'staging', // Parâmento opcional para configurar o ambiente "staging" ou "production", por padrão o valor é "production" (opcional)
    'clientId'            => '', // Client ID fornecido pelo Gov.br (obriggatório)
    'clientSecret'        => '', // Senha fornecida pelo Gov.br (obrigatório)
    'redirectUri'         => "", // Url de redirecionamento cadastrada no Gov.br para log in (obrigatório)
    'redirectUriLogout'   => "" // Url de redirecionamento cadadastrada no Gov.br para log out (obrigatório)
 ];

try {
    // Cria a instância de autenticação com o Gov.br
    $govBr = new \Soarescbm\OAuth2\Client\Provider\GovBr($config);

} catch (Exception $e) {

    exit("Erro na criação da instância Gov.br (" . $e->getMessage() . ")");
}


// Verifica se é uma requisição de redirecionamento para autenticação originada pelo Gov.br (callback), caso não seja, direcionada para autenticação no Gov.br.
// Entra nessa condição a ação do botão "Entrar com Gov.br".
if (!isset($_GET['code'])) {

    // Url com todos os parâmentro necessários para requesitar página de autenticação do Gov.br.
    $urlGovBr = $govBr->getAuthorizationUrl();
    // Por sergurança cria um state (codigo único randomico) para verificação da origem da requiquição de redirecionamento.
    $_SESSION['auth_govbr'] = $govBr->getState();
    // Redireciona para a página de autenticação do Gov.br para iniciar o processo.
    header('Location: '.$urlGovBr);
    exit();

// Verifica se o state fornecido  no redicioramento originado pelo Gov.br é o mesmo armanezando na sessão de inicio da autenticação, para mitigar ataque CSRF.
} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['auth_govbr'])) { 
   
    $_SESSION['auth_govbr'] = null;
    exit('Inválida tentativa de autenticação (state inválido)');

} else {

    
    try {
        // tenta obter o token de acesso usuando o code de autorização.
        $token = $govBr->getAccessToken('authorization_code', [
            'code' => $_GET['code']
        ]);

        // Com o token do usuário autenticado, obtém os detalhes do usuário cadastrado no Gov.br.
        /** @var \Soarescbm\OAuth2\Client\Provider\GovBrResourceOwner */
        $user = $govBr->getResourceOwner($token);

        $data = [
            'cpf' => $user->getCpf(),
            'nome' => $user->getName(),
            'email' => $user->getEmail(),
            'telefone' => $user->getPhoneNumber(),
            'cnpj' => $user->getCnpj(),  //Retorna o CNPJ vinculado ao usuário autenticado. Atributo será preenchido quando autenticação ocorrer por certificado digital de pessoal jurídica.
            
        ];

        // A título de exemplo, será apresentado no resultado da página, as informações no formato json caso seja executado com sucesso esse exemplo de fluxo.
        header('Content-type: application/json');
        echo json_encode($data);
        exit();


        // Retorna os dados das pessoas júridicas vinculados ao cpf do usuário autenticado
        // $empresas = $govBr->getCompanies($user);
         
        // Caso usuário tenha empresas vinculadas no seu cpf, o resultado será uma lista no formato abaixo
        // [
        //     {
        //     "cnpj": "(Número de CNPJ da empresa vinculada)",
        //     "razaoSocial": "(Razão Social (Nome da empresa) cadastrada na Receita Federal)",
        //     "dataCriacao": "(Mostra a data e hora da vinculação do CNPJ a conta do usuário. A mascará será YYYY-MM-DD HH:MM:SS)"
        //     }
        // ]


    } catch (Exception $e) {
        exit("Erro na obtenção dos dados do usuário autenticado no Gov.br. (" . $e->getMessage() . ")");
    }

    // A partir daqui,  com os dados do usário autenticado no Gov.br,  é possível autenticar o usuário no sistema próprio, caso já tenha cadastro, 
    // identificando o mesmo por cpf ou cnpj (No caso de autenticação por certicado de pessoa jurídicada cadastrada no Gov.br ou consultando os CNPJs vinculado ao cpf).
    // Como também iniciar o cadastro de um nova pessoa física ou jurídica que não possua cadastro.
  
    

```

## Credencias para Login Único Gov.br

As crendecias "CLIENT_ID/CLIENT_SECRET" são específicas para cada aplicação, devendo ser obtidas junto ao Gov.br, conforme roteiro disponível no link abaixo.

- [Manual de Integração Login Únicao Gov.br](https://manual-roteiro-integracao-login-unico.servicos.gov.br)
- [Roteiro para Socilicitação de Credenciais para Login Únicao Gov.br](https://manual-roteiro-integracao-login-unico.servicos.gov.br/pt/stable/solicitarconfiguracao.html)


# Execuntando o Código do Fluxo Padrão de Autenticação  (Opcional)


Com as credenciais disponibilizadas pelo Gov.Br, altere as insfomações de configuração no arquivo login_govbr_exemplo_fluxo.php.


```
$config = [
    'clientEnv'           => 'staging', // Parâmento opcional para configurar o ambiente "staging" ou "production", por padrão o valor é "production" (opcional)
    'clientId'            => '', // Client ID fornecido pelo Gov.br (obriggatório)
    'clientSecret'        => '', // Senha fornecida pelo Gov.br (obrigatório)
    'redirectUri'         => "", // Url de redirecionamento cadastrada no Gov.br para log in (obrigatório)
    'redirectUriLogout'   => "" // Url de redirecionamento cadadastrada no Gov.br para log out (obrigatório)
 ];

```


Com o docker instalado, execute o comando abaixo:

Como será feito o mapeamento das portas 80 e 443 da sua máquina para o container docker, será necessário interroper algum servidor local que esteja fazendo uso das portas 80 e 433.


```
docker-compose up -d --build

```

Com o container docker sendo executado, acesso pelo terminal o mesmo o mesmo e execute os camandos abaixo, para carregara as dependências do composer.


```
docker exec -it login-govbr   bash

```

Já dentro do terminal do container, atualize as dependências pelo composer.

```
composer install

```



## Apontamento local do domínio cadastrado na solicitação das credencias do gov.br


Será necessário fazer um apontamento local do domínio da aplicação cadastrado no gov.br para executar o fluxo de autenticação de exemplo:

Como exemplo, em ambiente o Linux, esse apontamento pode ser feito no arquivo /etc/hosts.

Exemplo:


```
...
127.0.0.1       meu-dominio.com.br
...

```

## Etapa Final

Abra o navegador e digite o seu domínio "meu-dominio.com.br", se tudo ocorrer como o previsto, você será direcionado para a autenticação com o Gov.Br.