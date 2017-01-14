<?php

/**
 * Class to handle all db operations
 * This class will have CRUD methods for database tables
 *
 * @author Ravi Tamada
 * @link URL Tutorial link
 */
class DbHandler {

    private $conn;

    function __construct() {
        require_once dirname(__FILE__) . '/DbConnect.php';
        // opening db connection
        $db = new DbConnect();
        $this->conn = $db->connect();
    }

    /* ------------- `users` table method ------------------ */

    /**
     * Creating new user
     * @param String $name User full name
     * @param String $email User login email id
     * @param String $password User login password
     */
    public function createUser($name, $email, $password, $uf) {
        require_once 'PassHash.php';
        $response = array();

        // First check if user already existed in db
        if (!$this->isUserExists($email)) {
            // Generating password hash
            $password_hash = PassHash::hash($password);

            // Generating API key
            $api_key = $this->generateApiKey();

            // insert query
            $stmt = $this->conn->prepare("INSERT INTO users(name, email, password_hash, api_key, status, uf) values(?, ?, ?, ?, 1, ?)");
            $stmt->bind_param("sssss", $name, $email, $password_hash, $api_key, $uf);

            $result = $stmt->execute();

            $stmt->close();

            // Check for successful insertion
            if ($result) {
                // User successfully inserted
                return USER_CREATED_SUCCESSFULLY;
            } else {
                // Failed to create user
                return USER_CREATE_FAILED;
            }
        } else {
            // User with same email already existed in the db
            return USER_ALREADY_EXISTED;
        }

        return $response;
    }

    /**
     * Checking user login
     * @param String $email User login email id
     * @param String $password User login password
     * @return boolean User login status success/fail
     */
    public function checkLogin($email, $password) {
        // fetching user by email
        $stmt = $this->conn->prepare("SELECT password_hash FROM users WHERE email = ?");

        $stmt->bind_param("s", $email);

        $stmt->execute();

        $stmt->bind_result($password_hash);

        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            // Found user with the email
            // Now verify the password

            $stmt->fetch();

            $stmt->close();

            if (PassHash::check_password($password_hash, $password)) {
                // User password is correct
                return TRUE;
            } else {
                // user password is incorrect
                return FALSE;
            }
        } else {
            $stmt->close();

            // user not existed with the email
            return FALSE;
        }
    }

    /**
     * Checking for duplicate user by email address
     * @param String $email email to check in db
     * @return boolean
     */
    private function isUserExists($email) {
        $stmt = $this->conn->prepare("SELECT id from users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    /**
     * Fetching user by email
     * @param String $email User email id
     */
    public function getUserByEmail($email) {
        $stmt = $this->conn->prepare("SELECT name, email, api_key, status, created_at FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        if ($stmt->execute()) {
            // $user = $stmt->get_result()->fetch_assoc();
            $stmt->bind_result($name, $email, $api_key, $status, $created_at);
            $stmt->fetch();
            $user = array();
            $user["name"] = $name;
            $user["email"] = $email;
            $user["api_key"] = $api_key;
            $user["status"] = $status;
            $user["created_at"] = $created_at;
            $stmt->close();
            return $user;
        } else {
            return NULL;
        }
    }

    /**
     * Fetching user api key
     * @param String $user_id user id primary key in user table
     */
    public function getApiKeyById($user_id) {
        $stmt = $this->conn->prepare("SELECT api_key FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            // $api_key = $stmt->get_result()->fetch_assoc();
            // TODO
            $stmt->bind_result($api_key);
            $stmt->close();
            return $api_key;
        } else {
            return NULL;
        }
    }

    /**
     * Fetching user id by api key
     * @param String $api_key user api key
     */
    public function getUserId($api_key) {
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE api_key = ?");
        $stmt->bind_param("s", $api_key);
        if ($stmt->execute()) {
            $stmt->bind_result($user_id);
            $stmt->fetch();
            // TODO
            // $user_id = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $user_id;
        } else {
            return NULL;
        }
    }

    /**
     * Validating user api key
     * If the api key is there in db, it is a valid key
     * @param String $api_key user api key
     * @return boolean
     */
    public function isValidApiKey($api_key) {
        $stmt = $this->conn->prepare("SELECT id from users WHERE api_key = ?");
        $stmt->bind_param("s", $api_key);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    /**
     * Generating random Unique MD5 String for user Api key
     */
    private function generateApiKey() {
        return md5(uniqid(rand(), true));
    }

    /* ------------- `parecer` table method ------------------ */

    /**
     * Creating new parecer
     */
    public function createParecer($id_user, $id_consulta, $estrelas, $voto, $comentario) {
        if(!$this->statusParecer($id_user, $id_consulta)){
            $stmt = $this->conn->prepare("INSERT INTO parecer(id_usuario, id_consulta, estrelas, voto, comentario) VALUES(?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $id_user, $id_consulta, $estrelas, $voto, $comentario);
            $result = $stmt->execute();
            $stmt->close();
        }else{
            $result = NULL;
        }

        if ($result) {
            // parecer success to create
            return true;
        } else {
            // parecer failed to create
            return false;
        }
    }
    
    
    /**
     * Checking for user parece
     * @param String $id_usuario 
     * @param String $id_consulta 
     * @return boolean
     */
    public function statusParecer($id_user, $id_consulta) {
        $stmt = $this->conn->prepare("SELECT id from parecer WHERE id_usuario = ? AND id_consulta = ?");
        $stmt->bind_param("ss", $id_user, $id_consulta);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }
    
    /**
     * Fetching user by email
     * @param String $email User email id
     */
    public function getConsultaByNome($nome_consulta) {
        $stmt = $this->conn->prepare("SELECT id, autor, explicacao_ementa, nome, ementa FROM consulta WHERE nome = ? AND status = 1");
        $stmt->bind_param("s", $nome_consulta);
        if ($stmt->execute()) {
            // $user = $stmt->get_result()->fetch_assoc();
            $stmt->bind_result($id, $autor, $explicacao_ementa, $nome, $ementa);
            $stmt->fetch();
            $consulta = array();
            if($id != NULL){
                $consulta["id"] = $id;
                $consulta["autor"] = $autor;
                $consulta["explicacao_ementa"] = $explicacao_ementa;
                $consulta["nome"] = $nome;
                $consulta["ementa"] = $ementa;
            }else{
                $consulta = NULL;
            }
            $stmt->close();
            return $consulta;
        } else {
            return NULL;
        }
    }
    
     /**
     * Fetching user by email
     * @param String $email User email id
     */
    public function getParecerByConsulta($id_user, $nome_consulta) {
        $consulta = array();
        $tmp = $this->getConsultaByNome($nome_consulta);
        if($tmp == NULL) return NULL;
        $consulta["id"] = $tmp['id'];
        $consulta["autor"] = $tmp['autor'];
        $consulta["explicacao_ementa"] = $tmp['explicacao_ementa'];
        $consulta["nome"] = $tmp['nome'];
        $consulta["ementa"] = $tmp['ementa'];
        $consulta["estrelas"] = "";
        $consulta["voto"] = "";
        $consulta["comentario"] = "";
        $stmt = $this->conn->prepare("SELECT estrelas, voto, comentario FROM parecer WHERE id_usuario = ? AND id_consulta = ?");
        $stmt->bind_param("ss", $id_user, $tmp['id']);
        $stmt->execute();
        // $user = $stmt->get_result()->fetch_assoc();
        $stmt->bind_result($estrelas, $voto, $comentario);
        $stmt->fetch();
        if($estrelas != NULL){
            $consulta["estrelas"] = $estrelas;
            $consulta["voto"] = $voto;
            $consulta["comentario"] = $comentario;
        }
        $stmt->close();
        return $consulta;
    }
    
    /**
     * Fetching user by email
     * @param String $email User email id
     */
    public function getParecerId($id_consulta) {
        $stmt = $this->conn->prepare("SELECT c.id, c.autor, c.explicacao_ementa, c.nome, c.ementa FROM consulta c WHERE c.id = ? AND status = 1");
        $stmt->bind_param("s", $id_consulta);
        if ($stmt->execute()) {
            // $user = $stmt->get_result()->fetch_assoc();
            $stmt->bind_result($id, $autor, $explicacao_ementa, $nome, $ementa);
            $stmt->fetch();
            $consulta = array();
            if($id != NULL){
                $consulta["id"] = $id;
                $consulta["autor"] = $autor;
                $consulta["explicacao_ementa"] = $explicacao_ementa;
                $consulta["nome"] = $nome;
                $consulta["ementa"] = $ementa;
            }else{
                return NULL;
            }
            $stmt->close();
        } else {
            return NULL;
        }
        
        $consulta["qteestrela1"] = 0;
        $consulta["qteestrela2"] = 0;
        $consulta["qteestrela3"] = 0;
        $consulta["qteestrela4"] = 0;
        $consulta["qteestrela5"] = 0;
        $consulta["qtesim"] = 0;
        $consulta["qtenao"] = 0;
        $consulta['comentarios'] = array();
        $stmt = $this->conn->prepare("SELECT estrelas, voto, comentario FROM parecer WHERE id_consulta = ?");
        $stmt->bind_param("s", $id_consulta);
        if ($stmt->execute()) {
           $result = $stmt->get_result();
           while ($item = $result->fetch_assoc()) {
               switch ($item['estrelas']) {
                    case 1:
                        $consulta["qteestrela1"]++;
                        break;
                    case 2:
                        $consulta["qteestrela2"]++;
                        break;
                    case 3:
                        $consulta["qteestrela3"]++;
                        break;
                    case 4:
                        $consulta["qteestrela4"]++;
                        break;
                    case 5:
                        $consulta["qteestrela5"]++;
                        break;
                }
                switch ($item['voto']) {
                    case 0:
                        $consulta["qtesim"]++;
                        break;
                    case 1:
                        $consulta["qtenao"]++;
                        break;
                }
                array_push($consulta['comentarios'], $item['comentario']);
           }
            $stmt->close();
            return $consulta;
        } else {
            return NULL;
        }
    }

    /**
     * Fetching all Query
     */
    public function getAllQuerys() {
        $stmt = $this->conn->prepare("SELECT c.* FROM consulta c WHERE c.status = 1");
        $stmt->execute();
        $query = $stmt->get_result();
        $stmt->close();
        return $query;
    }
    
    /**
     * Fetching all Query
     */
    public function getAllQuerysName() {
        $stmt = $this->conn->prepare("SELECT c.id, c.nome FROM consulta c WHERE c.status = 1");
        $stmt->execute();
        $query = $stmt->get_result();
        $stmt->close();
        return $query;
    }

    /**
     * Updating task
     * @param String $task_id id of the task
     * @param String $task task text
     * @param String $status task status
     */
    public function updateTask($user_id, $task_id, $task, $status) {
        $stmt = $this->conn->prepare("UPDATE tasks t, user_tasks ut set t.task = ?, t.status = ? WHERE t.id = ? AND t.id = ut.task_id AND ut.user_id = ?");
        $stmt->bind_param("siii", $task, $status, $task_id, $user_id);
        $stmt->execute();
        $num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
    }

    /**
     * Deleting a task
     * @param String $task_id id of the task to delete
     */
    public function deleteTask($user_id, $task_id) {
        $stmt = $this->conn->prepare("DELETE t FROM tasks t, user_tasks ut WHERE t.id = ? AND ut.task_id = t.id AND ut.user_id = ?");
        $stmt->bind_param("ii", $task_id, $user_id);
        $stmt->execute();
        $num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
    }

    /* ------------- `user_tasks` table method ------------------ */

    /**
     * Function to assign a task to user
     * @param String $user_id id of the user
     * @param String $task_id id of the task
     */
    public function createUserTask($user_id, $task_id) {
        $stmt = $this->conn->prepare("INSERT INTO user_tasks(user_id, task_id) values(?, ?)");
        $stmt->bind_param("ii", $user_id, $task_id);
        $result = $stmt->execute();

        if (false === $result) {
            die('execute() failed: ' . htmlspecialchars($stmt->error));
        }
        $stmt->close();
        return $result;
    }

}

?>
