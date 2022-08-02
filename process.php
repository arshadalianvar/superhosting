<?php
	// var_dump($_POST);
	
	$username = $_POST['username'];
	$password = $_POST['password'];
	$passwordRepeat = $_POST['passwordRepeat'];
	$email = $_POST['email'];

	// Best not to use this...
	// foreach ($_POST as $key => $value) $$key = $value;

	// check if passwords match
	if ($password !== $passwordRepeat) {
		header('Location: index.html?error=password-mismatch');
		exit();
	}

	$allowed_salt = "abcdefghiklmnopqrstuvwxyzABCDEFGHJKLMNOPQRSTUVWXYZ0123456789";
	$salt = substr($allowed_salt, rand(0,strlen($allowed_salt)-1), 1).substr($allowed_salt, rand(0,strlen($allowed_salt)-1), 1);
	$passwordEncrypted = crypt($password, $salt);

	$today = date('Y-m-d H-i-s');
	$command = "sudo /usr/sbin/useradd " .
		"-c \"Created using PHP on $today\" " .
		"-G clients " .
		"-k /home/rdinita/skel " .
		"-m " .
		"-p $passwordEncrypted " .
		$username;

	exec($command, $result, $exitCode);
	$logLine = date(DATE_ATOM) . "\nShell ($command)\nResult: " . implode($result) . "\nExit Code: $exitCode\n\n";
	file_put_contents('/home/rdinita/public_html/registration.log', $logLine, FILE_APPEND);

	if ($exitCode !== 0) {
		header('Location: index.html?error=user-' . $exitCode);
                exit();
	}

	$host = '127.0.0.1';
	$dbuser = 'apache';
	$dbpass = 'apache135!';

	//Establishes the connection
	$conn = mysqli_init();
	mysqli_real_connect($conn, $host, $dbuser, $dbpass);
	
	if (mysqli_connect_errno($conn)) {
		header('Location: index.html?error=sql-' . mysqli_connect_error());
                exit();
	}

	// sanitize user inputs
	$username = mysqli_real_escape_string($conn, $username);
	$password = mysqli_real_escape_string($conn, $password);
	$email = mysqli_real_escape_string($conn, $email);

	// db related variables
	$prefix = 'apache_';
	$userPrefix = $prefix.$username;

	$query = "create database if not exists $userPrefix";

	if (!mysqli_query($conn, $query)) {
		die("Error with '$query': " . mysqli_error($conn));
	}

	// only create a user if not there
	$query = "create user if not exists '$username'@'%' identified by '$password'";

	if (!mysqli_query($conn, $query)) {
                die("Error with '$query': " . mysqli_error($conn));
        }

	// grant db & table privileges
	// note: can only grant permissions that the apache MySQL user already has, 
	// 	 and 'grant all privileges' would cover more privileges than available, so does not work
	$query = "grant LOCK TABLES, ALTER, CREATE VIEW, CREATE, DELETE, DROP, GRANT OPTION, INDEX, INSERT, REFERENCES, SELECT, SHOW VIEW, TRIGGER, UPDATE on $userPrefix.* to '$username'@'%'";

	if (!mysqli_query($conn, $query)) {
                die("Error with '$query': " . mysqli_error($conn));
        }

	mysqli_close($conn);

	// email confirmation
	if ($email) {
		$subject = "SuperHosting - Account Ready";
		$message = "Hello $username!\n\nYour account is now ready to be used. You may now SSH into your acount using the credentials you previously supplied during Registration.\n\nEnjoy,\nThe SuperHosting Team.";
		$headers = [
			"From" => "no-reply@superhosting.co.uk",
			"X-Mailer" => "PHP/" . phpversion()
		];
		
		$emailSent = mail($email, $subject, $message, $headers);

		if (!$emailSent) {
			header('Location: index.html?error=account-created-but-email-not-sent');
			exit();
		}
	}

	// all done
	header('Location: index.html?success=ok');
	exit();
