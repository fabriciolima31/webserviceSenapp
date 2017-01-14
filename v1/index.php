<?php

require_once '../include/DbHandler.php';
require_once '../include/PassHash.php';
require '.././libs/Slim/Slim.php';

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

// User id from db - Global Variable
$user_id = NULL;

/**
 * Adding Middle Layer to authenticate every request
 * Checking if the request has valid api key in the 'Authorization' header
 */
function authenticate(\Slim\Route $route) {
    // Getting request headers
    $headers = apache_request_headers();
    $response = array();
    $app = \Slim\Slim::getInstance();
    // Verifying authorization Header
    if (isset($headers['authorization'])) {
        $db = new DbHandler();

        // get the api key
        $api_key = $headers['authorization'];
        // validating api key
        if (!$db->isValidApiKey($api_key)) {
            // api key is not present in users table
            $response["error"] = true;
            $response["message"] = "Access Denied. Invalid Api key";
            echoRespnse(401, $response);
            $app->stop();
        } else {
            global $user_id;
            // get user primary key id
            $user_id = $db->getUserId($api_key);
        }
    } else {
        // api key is missing in header
        $response["error"] = true;
        $response["message"] = "Api key is misssing";
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * ----------- METHODS WITHOUT AUTHENTICATION ---------------------------------
 */
/**
 * User Registration
 * url - /register
 * method - POST
 * params - name, email, password, uf
 */
$app->post('/register', function() use ($app) {
            // check for required params
            verifyRequiredParams(array('name', 'email', 'password'));

            $response = array();

            // reading post params
            $name = $app->request->post('name');
            $email = $app->request->post('email');
            $password = $app->request->post('password');
            $uf = $app->request->post('uf');

            // validating email address
            validateEmail($email);

            $db = new DbHandler();
            $res = $db->createUser($name, $email, $password, $uf);

            if ($res == USER_CREATED_SUCCESSFULLY) {
                $response["error"] = false;
                $response["message"] = "Você foi registrado com Sucesso!";
            } else if ($res == USER_CREATE_FAILED) {
                $response["error"] = true;
                $response["message"] = "Oops! Ocorreu um erro durante o registro";
            } else if ($res == USER_ALREADY_EXISTED) {
                $response["error"] = true;
                $response["message"] = "Desculpe, esse endereço de e-mail já existe";
            }
            // echo json response
            echoRespnse(201, $response);
        });

/**
 * User Login
 * url - /login
 * method - POST
 * params - email, password
 */
$app->post('/login', function() use ($app) {
            // check for required params
            verifyRequiredParams(array('email', 'password'));

            // reading post params
            $email = $app->request()->post('email');
            $password = $app->request()->post('password');
            $response = array();

            $db = new DbHandler();
            // check for correct email and password
            if ($db->checkLogin($email, $password)) {
                // get the user by email
                $user = $db->getUserByEmail($email);

                if ($user != NULL) {
                    $response["error"] = false;
                    $response['name'] = $user['name'];
                    $response['email'] = $user['email'];
                    $response['apiKey'] = $user['api_key'];
                    $response['createdAt'] = $user['created_at'];
                } else {
                    // unknown error occurred
                    $response['error'] = true;
                    $response['message'] = "Ocorreu um Erro. Por Favor, Tente Novamente";
                }
            } else {
                // user credentials are wrong
                $response['error'] = true;
                $response['message'] = 'Falha ao Logar. E-mail ou Senha Incorretos';
            }

            echoRespnse(200, $response);
        });

/*
 * ------------------------ METHODS WITH AUTHENTICATION ------------------------
 */

/**
 * Listing all querys names
 * method GET
 */
$app->get('/listConsulta', 'authenticate', function() {
            global $user_id;
            $response = array();
            $db = new DbHandler();

            // fetching all user tasks
            $result = $db->getAllQuerysName();

            $response["error"] = false;
            $response["query"] = [];

            // looping through result and preparing tasks array
            while ($query = $result->fetch_assoc()) {
                $tmp = array();
                array_push($response["query"], $query["nome"]);
            }

            echoRespnse(200, $response);
        });


/**
 * Creating new parecer in db
 * method POST
 * params - nome
 * url - /parecer/
 */
$app->post('/darParecer', 'authenticate', function() use ($app) {
            // check for required params
            verifyRequiredParams(array('id_consulta', 'estrelas', 'voto', 'comentario'));

            $response = array();
            $id_consulta = $app->request->post('id_consulta');
            $estrelas = $app->request->post('estrelas');
            $voto = $app->request->post('voto');
            $comentario = $app->request->post('comentario');

            global $user_id;
            $db = new DbHandler();

            // creating new parecer
            $parecer_id = $db->createParecer($user_id, $id_consulta, $estrelas, $voto, $comentario);

            if ($parecer_id) {
                $response["error"] = false;
                $response["message"] = "Parecer salvo com sucesso!";
                echoRespnse(201, $response);
            } else {
                $response["error"] = true;
                $response["message"] = "Falha ao salvar parecer. Por favor tente novamente";
                echoRespnse(200, $response);
            }            
        });


 /**
 * Return all info parecer for id
 * method GET
 * url /buscarParecer/:id
 */
$app->get('/estatisticaConsulta/:id', 'authenticate', function($id_consulta) {
            global $user_id;
            $response = array();
            $db = new DbHandler();

            // fetch parecer
            $result = $db->getParecerId($id_consulta);

            if ($result != NULL) {
                $response = $result;
            } else {
                // unknown error occurred
                $response['error'] = true;
                $response['message'] = "Ocorreu um Erro. Por Favor, Tente Novamente";
            }
            echoRespnse(200, $response);
        });


/**
 * Return status parecer
 * method GET
 * url /statusParecer/:id
 */
$app->get('/statusParecer/:id', 'authenticate', function($id_consulta) {
            global $user_id;
            $response = array();
            $db = new DbHandler();

            // fetch parecer
            $result = $db->statusParecer($user_id, $id_consulta);

            if ($result == 0) {
                $response["status"] = 0;
                
            } else {
                $response["status"] = 1;
            }
            echoRespnse(200, $response);
        });
        
 /**
 * Return all info parecer for nome_consulta
 * method POST
 * params - nome_consulta
 * url - /parecer/
 */
$app->post('/infoConsulta', 'authenticate', function() use ($app) {
            // check for required params
            verifyRequiredParams(array('nome_consulta'));

            $response = array();
            $nome_consulta = $app->request->post('nome_consulta');

            //global $user_id;
            $db = new DbHandler();

            // creating new parecer
            $consulta = $db->getConsultaByNome($nome_consulta);

            if ($consulta) {
                $response["error"] = false;
                $response["id"] = $consulta['id'];
                $response["autor"] = $consulta['autor'];
                $response["explicacao_ementa"] = $consulta['explicacao_ementa'];
                $response["nome"] = $consulta['nome'];
                $response["ementa"] = $consulta['ementa'];
                echoRespnse(200, $response);
            } else {
                $response["error"] = true;
                $response["message"] = "Consulta Não Encontrada.";
                echoRespnse(200, $response);
            }            
        });
        
$app->post('/infoParecer', 'authenticate', function() use ($app) {
        // check for required params
        verifyRequiredParams(array('nome_consulta'));

        $response = array();
        $nome_consulta = $app->request->post('nome_consulta');

        global $user_id;
        $db = new DbHandler();

        // creating new parecer
        $consulta = $db->getParecerByConsulta($user_id, $nome_consulta);

        if ($consulta != NULL) {
            $response["error"] = false;
            $response["id"] = $consulta['id'];
            $response["autor"] = $consulta['autor'];
            $response["explicacao_ementa"] = $consulta['explicacao_ementa'];
            $response["nome"] = $consulta['nome'];
            $response["ementa"] = $consulta['ementa'];
            $response["estrelas"] = $consulta['estrelas'];
            $response["voto"] = $consulta['voto'];
            $response["comentario"] = $consulta['comentario'];
            echoRespnse(200, $response);
        } else {
            $response["error"] = true;
            $response["message"] = "Consulta Não Encontrada.";
            echoRespnse(200, $response);
        }            
    });
        
/**
 * Verifying required params posted or not
 */
function verifyRequiredParams($required_fields) {
    $error = false;
    $error_fields = "";
    $request_params = array();
    $request_params = $_REQUEST;
    // Handling PUT request params
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $app = \Slim\Slim::getInstance();
        parse_str($app->request()->getBody(), $request_params);
    }
    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }

    if ($error) {
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $response = array();
        $app = \Slim\Slim::getInstance();
        $response["error"] = true;
        $response["message"] = 'Campos Obrigatórios ' . substr($error_fields, 0, -2) . ' estão Em Branco';
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * Validating email address
 */
function validateEmail($email) {
    $app = \Slim\Slim::getInstance();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["error"] = true;
        $response["message"] = 'Email address is not valid';
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * Echoing json response to client
 * @param String $status_code Http response code
 * @param Int $response Json response
 */
function echoRespnse($status_code, $response) {
    $app = \Slim\Slim::getInstance();
    // Http response code
    $app->status($status_code);

    // setting response content type to json
    $app->contentType('application/json');

    echo json_encode($response);
}

$app->run();
?>