<?php

/**
 * Blog install function
 * @return array(count array, error string)
 */
function installBlog(PDO $pdo) {
    // Get a couple of useful project paths
    $root = getRootPath();
    $db = getDatabasePath();

    $error = '';

    // A security measure, to avoid anyone resetting the database if it already exists
    if (is_readable($db) && filesize($db) > 0) {
        $error = 'Please delete the existing database manually before installing it afresh';
    }

    // Create an empty file for the database
    if (!$error) {
        $createdOk = @touch($db);
        if (!$createdOk) {
            $error = sprintf(
                'Could not create the database, please allow the server to create new files in \'%s\'',
                dirname($db)
            );
        }
    }

    // Grab the SQL commands we want to run on the database
    if (!$error) {
        $sql = file_get_contents($root . '/data/init.sql');
        if ($sql === false) {
            $error = 'Cannot find SQL file';
        }
    }

    // Connect to the new database and try to run the SQL commands
    if (!$error) {
        $result = $pdo->exec($sql);
        if ($result === false) {
            $error = 'Could not run SQL: ' . print_r($pdo->errorInfo(), true);
        }
    }

    // See how many rows we created, if any
    $count = array();

    foreach (array('post', 'comment') as $tableName) {

        if (!$error) {
            $sql = "SELECT COUNT(*) AS c FROM " . $tableName;
            $stmt = $pdo->query($sql);
            if ($stmt) {
                // We sotre each count in an associative array
                $count[$tableName] = $stmt->fetchColumn();
            }
        }
    }

    return array($count, $error);
}


/**
 * Updates the admin user in the database
 * @param PDO $pdo
 * @param string $email
 * @param integer $length
 * @return array Duple of (password, error)
 */
function createUser(PDO $pdo, $email, $length = 10) {

    // This alogirthm creates a random password
    $alphabet = range(ord('A'), ord('z'));
    $alphabetLength = count($alphabet);

    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $letterCode = $alphabet[rand(0, $alphabetLength - 1)];
        $password .= chr($letterCode);
    }

    $error = '';

    // Insert the credentials into the database
    $sql = "
        UPDATE
            user
        SET
            password = :password, created_at = :created_at, is_enabled = 1
        WHERE
            email = :email
    ";

    $stmt = $pdo->prepare($sql);
    if ($stmt === false) {
        $error = 'Could not prepare the user update';
    }

    // We're storing the password in plaintext, will fix that later
    if (!$error) {
        // Create a has of the password
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            $error = 'Password hashing failed';
        }
    }

    if (!$error) {
        $result = $stmt->execute(
            array(
                'email' => $email,
                'password' => $hash,
                'created_at' => getSqlDateForNow(),
            )
        );

        if ($result === false) {
            $error = 'Could not run the user password update';
        }
    }

    if ($error) {
        $password = '';
    }

    return array($password, $error);
}

?>
