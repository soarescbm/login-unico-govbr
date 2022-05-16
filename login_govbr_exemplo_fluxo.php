<?php

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

// Verifica se o state fornecido  no redicioramento originado pelo Gov.br é o mesmo armanezando na sessão de inicio da autentição, para mitigar ataque CSRF.
} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['auth_govbr'])) { 
   
    $_SESSION['auth_govbr'] = null;
    exit('Inválida tentativa de autenticação (state inválido)');

} else {

    
    try {
        // tenta obter o token de acesso usuando o code de autorização.
        $token = $govBr->getAccessToken('authorization_code', [
            'code' => $_GET['code']
        ]);

        // Com o token do usuário autenticado, obtem os detalhes do usuário cadastrado no Gov.br.
        /** @var \Soarescbm\OAuth2\Client\Provider\GovBrResourceOwner */
        $user = $govBr->getResourceOwner($token);

        $data = [
            'cpf' => $user->getCpf(),
            'nome' => $user->getName(),
            'email' => $user->getEmail(),
            'telefone' => $user->getPhoneNumber(),
            'cnpj' => $user->getCnpj(),  //Retorna o CNPJ vinculado ao usuário autenticado. Atributo será preenchido quando autenticação ocorrer por certificado digital de pessoal jurídica.
            
        ];

        // A título de exemplo, será apresentado no resultado da página, as informação no formato json caso seja executado com sucesso esse exemplo de fluxo.
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
  
    
}




