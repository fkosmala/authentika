<?php

use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use PHPMailer\PHPMailer\PHPMailer;

require __DIR__ . '/../vendor/autoload.php';

// Create Container
$container = new Container();
$container->set('upload_dir', __DIR__ . '/../uploads/');
$container->set('cert_dir', __DIR__ . '/../certs/');
AppFactory::setContainer($container);

// Set view in Container
$container->set('view', function() {
    return Twig::create(__DIR__.'/../views', ['cache' => false]);
});

// Create App
$app = AppFactory::create();

// Add Twig-View Middleware
$app->add(TwigMiddleware::createFromContainer($app));

// Define named route
$app->get('/', function ($request, $response, $args) {
  return $this->get('view')->render($response, 'index.twig');
})->setName('index');

$app->get('/start', function ($request, $response, $args) {
  return $this->get('view')->render($response, 'start.twig');
})->setName('start');

$app->post('/form', function ($request, $response, $args) {
  $data = $request->getParsedBody();
  $json = json_encode($data);

  // Generate random number to create the user part of JSON cert file
  $code = mt_rand(10000000, 99999999);
  $tmp = __DIR__.'/../certs/';
  $cert = $tmp.$code;
  file_put_contents($cert, $json);

  // Check if webserver have SSL cert or not
  if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
    $link = "https";
  else
    $link = "http";

  // Here append the common URL characters.
  $link .= "://";

  // Append the host(domain name, ip) to the URL.
  $link .= $_SERVER['HTTP_HOST'].'/cert/'.$code;

  // Create and send mail
  $mail = new PHPMailer;
  $mail->addReplyTo('cert@authentika.art', 'Authentika Cert Service');
  $mail->setFrom('cert@authentika.art', 'Authentika Cert Service');
  $mail->addAddress($data["email"], $data["firstName"]);
  $mail->Subject  = 'Validation Code for your file';
  $mail->Body     = "Hello ! This is your validation code : ".$code."\nClick on this link to upload the file and get your Copyright certificate : ".$link;
  if(!$mail->send()) {
    return $this->get('view')->render($response, 'mail-error.twig', [
      "error" => $mail->ErrorInfo
    ]);
  } else {
    return $this->get('view')->render($response, 'mail-send.twig');
  }

  return $this->get('view')->render($response, 'index.twig');
})->setName('form');

$app->get('/cert/{code}', function ($request, $response, $args) {
  if ((isset($args['code'])) && (strlen($args['code']) == 8)) {
    $code = $args['code'];
    return $this->get('view')->render($response, 'cert.twig', [
      "code" => $code
    ]);
  } else return $response->withHeader('Location', '/')->withStatus(302);
})->setName('cert');

$app->post('/upload', function ($request, $response, $args) {
  $data = $request->getParsedBody();

  if ((isset($data['code'])) && (strlen($data['code']) == 8)) {
    $code = $data['code'];
    $tmp = __DIR__.'/../certs/';
    if (!file_exists($tmp.$code)) {
      return $response->withHeader('Location', '/')->withStatus(302);
    }
    $cert = json_decode(file_get_contents($tmp.$code), true);
    if ($data['email'] != $cert['email']) {
      return $response->withHeader('Location', '/')->withStatus(302);
    }
    return $this->get('view')->render($response, 'upload.twig', [
      "code" => $code
    ]);
  }
})->setName('upload');

$app->post('/getcert', function ($request, $response, $args) {
  $data = $request->getParsedBody();
  $code = $data['code'];
  $dir = $this->get('upload_dir');

  $certs = $this->get('cert_dir');
  if (!file_exists($certs.$code)) {
    return $response->withHeader('Location', '/')->withStatus(302);
  }

  $cert = $certs.$code;

  $uploadedFiles = $request->getUploadedFiles();
  $uploadedFile = $uploadedFiles['filecert'];
  $uploadedFile->moveTo($dir.$uploadedFile->getClientFilename());
  $test = $uploadedFile->getClientFilename();
  $hash = hash_file('sha512', $dir.$uploadedFile->getClientFilename());

  $json = json_decode(file_get_contents($cert),true);
  
  // Add File part into the JSON cert file
  $json['file']['name'] = $uploadedFile->getClientFilename();
  $json['file']['size'] = $uploadedFile->getSize();
  $json['file']['type'] = $uploadedFile->getClientMediaType();
  $json['file']['hash'] = $hash;
  $mail = $json['email'];
  unset($json['email']);
  file_put_contents($cert, json_encode($json));
  
  $account_file = __DIR__.'/../account.json';
  
  $command = 'python '.__DIR__.'/../sendjson.py '.$cert.' '.$account_file.' 2>&1';
  ob_start();
  passthru($command);
  $result = ob_get_contents();
  ob_end_clean();
  $result = json_decode($result, true);
  $tx = $result['id'];
  
  $mail = new PHPMailer;
  $mail->addReplyTo('cert@authentika.art', 'Authentika Cert Service');
  $mail->setFrom('cert@authentika.art', 'Authentika Cert Service');
  $mail->addAddress($mail, $json["firstName"]);
  $mail->Subject  = 'Authentika - File Certification';
  $mail->Body     = "This is your Certificate #".$tx." for the file [".$json['file']['name']."]\n\n You can find your cert on the hive blockchain at this link :\nhttps://hiveblocks.com/tx/".$tx."\n\n-------- CERTIFICATE CONTENT --------\n".$cert;
  if(!$mail->send()) {
    $error = false;
  } else {
    $error = true;
  }
  
  unlink($cert);
  unlink($dir.$uploadedFile->getClientFilename());
  
  return $this->get('view')->render($response, 'getcert.twig', [
      "cert" => json_encode($json),
      "sign" => $json['file']['hash'],
      "tx" => $tx
    ]);

})->setName('getcert');

$app->post('/verify', function ($request, $response, $args) {
	$data = $request->getParsedBody();
	$uploadedFiles = $request->getUploadedFiles();
  $uploadedFile = $uploadedFiles['verify'];
  if (!isset($uploadedFiles)) {
    return $response->withHeader('Location', '/')->withStatus(302);
  }
  $dir = $this->get('upload_dir');
  $uploadedFile->moveTo($dir.$uploadedFile->getClientFilename());
  $hash = hash_file('sha512', $dir.$uploadedFile->getClientFilename());
  unlink($dir.$uploadedFile->getClientFilename());
  return $this->get('view')->render($response, 'verify.twig', [
		"sign" => $hash,
	]);
  
})->setName('verify');

// Run app
$app->run();
