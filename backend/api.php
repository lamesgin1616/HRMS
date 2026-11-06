<?php
declare(strict_types=1);
require_once __DIR__.'/config.php';
header('Vary: Origin');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, APP_ORIGINS, true)) {
    header('Access-Control-Allow-Origin: '.$origin);
    header('Access-Control-Allow-Credentials: true');
} elseif ($origin !== '') {
    http_response_code(403);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
session_start();
header('Content-Type: application/json; charset=utf-8');

function out($data, int $status=200): never { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); exit; }
function body(): array { $x=json_decode(file_get_contents('php://input'), true); return is_array($x)?$x:$_POST; }
function require_valid_password(string $password): void {
  $key=password_policy_error($password);
  if($key!==null)out(['error'=>'Choose a stronger password','translation_key'=>$key],422);
}
function require_login(): array {
  if(empty($_SESSION['user']['user_id']))out(['error'=>'Authentication required'],401);
  $s=db()->prepare('SELECT u.user_id,u.username,u.role,u.status,u.emp_id,COALESCE(e.full_name,u.username) full_name FROM users u LEFT JOIN employees e ON e.emp_id=u.emp_id WHERE u.user_id=?');
  $s->execute([(int)$_SESSION['user']['user_id']]);$fresh=$s->fetch();
  if(!$fresh||$fresh['status']!=='Active'){$_SESSION=[];session_destroy();out(['error'=>'Account is inactive or no longer available'],401);}
  $_SESSION['user']=['user_id'=>(int)$fresh['user_id'],'username'=>$fresh['username'],'role'=>$fresh['role'],'emp_id'=>$fresh['emp_id']!==null?(int)$fresh['emp_id']:null,'full_name'=>$fresh['full_name']];
  return $_SESSION['user'];
}
function require_role(array $roles): array { $u=require_login(); if(!in_array($u['role'],$roles,true)) out(['error'=>'Access denied'],403); return $u; }
function log_action(string $action, string $details=''): void { $u=$_SESSION['user']['user_id']??null; $s=db()->prepare('INSERT INTO activity_logs(user_id,action,details,ip_address) VALUES(?,?,?,?)'); $s->execute([$u,$action,$details,$_SERVER['REMOTE_ADDR']??null]); }
function ensure_user_accounts_schema(): void {
  $pdo=db();
  $statement=$pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?');
  $readColumns=static function(string $table) use ($statement): array {
    $statement->execute([$table]);
    return $statement->fetchAll(PDO::FETCH_COLUMN);
  };
  $users=$readColumns('users');
  $employees=$readColumns('employees');
  if(!$users) {
    out(['error'=>'The users table is missing. Import the users table definition from sql/mau_hrms.sql, then create an administrator account.','code'=>'users_table_missing'],503);
  }
  if(!$employees) {
    out(['error'=>'The employees table is missing. Import the MAU-HRMS database schema before using user accounts.','code'=>'employees_table_missing'],503);
  }

  $safeColumns=[
    'email'=>'ADD COLUMN email varchar(160) DEFAULT NULL',
    'phone'=>'ADD COLUMN phone varchar(40) DEFAULT NULL',
    'emp_id'=>'ADD COLUMN emp_id int(11) DEFAULT NULL',
    'failed_attempts'=>'ADD COLUMN failed_attempts tinyint(3) UNSIGNED NOT NULL DEFAULT 0',
    'locked_until'=>'ADD COLUMN locked_until datetime DEFAULT NULL',
    'created_at'=>'ADD COLUMN created_at timestamp NOT NULL DEFAULT current_timestamp()',
  ];
  $added=[];
  foreach($safeColumns as $column=>$definition) {
    if(in_array($column,$users,true))continue;
    try {
      $pdo->exec('ALTER TABLE users '.$definition);
      $added[]=$column;
    } catch(Throwable $error) {
      error_log('HRMS could not add users.'.$column.': '.$error->getMessage());
      out(['error'=>'The users table could not be updated. Check database permissions and apply the user schema migration.','code'=>'users_schema_update_failed','column'=>$column],503);
    }
  }
  if($added)error_log('HRMS users table migrated automatically; added columns: '.implode(', ',$added));

  $users=$readColumns('users');
  $employeeColumns=$readColumns('employees');
  $required=[
    'users'=>['user_id','username','password_hash','role','status','email','phone','emp_id','failed_attempts','locked_until','created_at'],
    'employees'=>['emp_id','full_name','staff_no'],
  ];
  $available=['users'=>$users,'employees'=>$employeeColumns];
  $missing=[];
  foreach($required as $table=>$columns) {
    foreach($columns as $column) {
      if(!in_array($column,$available[$table],true))$missing[]=$table.'.'.$column;
    }
  }
  if($missing) {
    error_log('HRMS user-account schema is missing: '.implode(', ',$missing));
    out([
      'error'=>'The user-account database schema is incomplete. Core identity and authentication columns must be added from sql/mau_hrms.sql; they cannot be safely inferred.',
      'code'=>'users_schema_outdated',
      'missing'=>$missing,
    ],503);
  }
}
function ensure_notifications_schema(): array {
  $pdo=db();
  $table=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
  $table->execute(['notifications']);
  if(!(int)$table->fetchColumn()) {
    try {
      $pdo->exec("CREATE TABLE notifications (
        id int(11) NOT NULL AUTO_INCREMENT,
        user_id int(11) NOT NULL,
        title varchar(160) NOT NULL DEFAULT 'MAU HRMS notification',
        message varchar(500) NOT NULL,
        type varchar(80) NOT NULL DEFAULT 'system',
        details_json longtext DEFAULT NULL,
        is_read tinyint(1) NOT NULL DEFAULT 0,
        created_at timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (id),
        KEY user_id (user_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
      error_log('HRMS notifications table created automatically.');
    } catch(Throwable $createError) {
      error_log('HRMS could not create notifications table: '.$createError->getMessage());
      out(['error'=>'The notifications table could not be created. Check database permissions.','code'=>'notifications_table_creation_failed'],503);
    }
  }

  $columns=[];
  foreach($pdo->query('SHOW COLUMNS FROM notifications')->fetchAll() as $column) {
    $columns[$column['Field']]=true;
  }
  if(!isset($columns['user_id'])||!isset($columns['message'])) {
    error_log('HRMS notifications table is missing required user_id or message columns.');
    out(['error'=>'The notifications table is missing required columns. Apply the notification database migration.','code'=>'notifications_schema_missing'],503);
  }
  if(!isset($columns['id'])&&!isset($columns['not_id'])) {
    error_log('HRMS notifications table has no id or not_id primary column.');
    out(['error'=>'The notifications table is missing its ID column. Apply the notification database migration.','code'=>'notifications_schema_missing'],503);
  }
  $alterations=[];
  if(!isset($columns['title']))$alterations[]="ADD COLUMN title varchar(160) NOT NULL DEFAULT 'MAU HRMS notification'";
  if(!isset($columns['type'])&&!isset($columns['event_type']))$alterations[]="ADD COLUMN type varchar(80) NOT NULL DEFAULT 'system'";
  if(!isset($columns['details_json']))$alterations[]='ADD COLUMN details_json longtext DEFAULT NULL';
  if(!isset($columns['is_read']))$alterations[]='ADD COLUMN is_read tinyint(1) NOT NULL DEFAULT 0';
  if(!isset($columns['created_at']))$alterations[]='ADD COLUMN created_at timestamp NOT NULL DEFAULT current_timestamp()';
  if($alterations) {
    try {
      $pdo->exec('ALTER TABLE notifications '.implode(', ',$alterations));
    } catch(Throwable $migrationError) {
      error_log('HRMS could not update notifications table: '.$migrationError->getMessage());
      out(['error'=>'The notifications table could not be updated. Check database permissions.','code'=>'notifications_schema_update_failed'],503);
    }
  }
  return [
    'id'=>isset($columns['id'])?'id':'not_id',
    'type'=>isset($columns['type'])||!isset($columns['event_type'])?'type':'event_type',
  ];
}
function notification_delivery_defaults(): array { return ['email_sent'=>false,'sms_sent'=>false]; }
function merge_notification_delivery(array $first,array $second): array { return ['email_sent'=>$first['email_sent']||$second['email_sent'],'sms_sent'=>$first['sms_sent']||$second['sms_sent']]; }
function dispatch_notification_email(?string $email,string $title,string $message,array $details,int $userId): bool {
  if(!$email||!filter_var($email,FILTER_VALIDATE_EMAIL)){error_log('HRMS email notification skipped: no valid email for user '.$userId.'.');return false;}
  $body=$message;
  if($details){
    $body.="\n\nDetails:";
    foreach($details as $key=>$value){
      if($value===null||$value==='')continue;
      $label=ucwords(str_replace('_',' ',(string)$key));
      $text=is_scalar($value)?(string)$value:json_encode($value,JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR);
      $body.="\n".$label.': '.$text;
    }
  }
  if(MAIL_SMTP_HOST!=='') {
    $autoload=__DIR__.'/vendor/autoload.php';
    if(is_file($autoload))require_once $autoload;
    if(!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
      error_log('HRMS SMTP notification failed: install PHPMailer in backend/vendor or configure PHP mail transport.');
      return false;
    }
    if(!filter_var(MAIL_FROM_EMAIL,FILTER_VALIDATE_EMAIL)) {
      error_log('HRMS SMTP notification failed: configure a valid MAIL_FROM_EMAIL.');
      return false;
    }
    try {
      $mailer=new \PHPMailer\PHPMailer\PHPMailer(true);
      $mailer->isSMTP();
      $mailer->Host=MAIL_SMTP_HOST;
      $mailer->Port=MAIL_SMTP_PORT;
      $mailer->SMTPAuth=MAIL_SMTP_USERNAME!=='';
      if($mailer->SMTPAuth){$mailer->Username=MAIL_SMTP_USERNAME;$mailer->Password=MAIL_SMTP_PASSWORD;}
      if(MAIL_SMTP_ENCRYPTION==='tls')$mailer->SMTPSecure=\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
      elseif(MAIL_SMTP_ENCRYPTION==='ssl')$mailer->SMTPSecure=\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
      elseif(MAIL_SMTP_ENCRYPTION!=='')throw new RuntimeException('MAIL_SMTP_ENCRYPTION must be tls, ssl, or empty.');
      $mailer->CharSet='UTF-8';
      $mailer->setFrom(MAIL_FROM_EMAIL,'MAU HRMS');
      $mailer->addAddress($email);
      $mailer->Subject='MAU HRMS: '.$title;
      $mailer->Body=$body;
      $mailer->send();
      return true;
    } catch(Throwable $error) {
      error_log('HRMS SMTP email notification failed for user '.$userId.': '.$error->getMessage());
      return false;
    }
  }
  if(!filter_var(MAIL_FROM_EMAIL,FILTER_VALIDATE_EMAIL)){error_log('HRMS email notification skipped: configure MAIL_FROM_EMAIL and PHP mail transport.');return false;}
  $headers="From: MAU HRMS <".MAIL_FROM_EMAIL.">\r\nContent-Type: text/plain; charset=UTF-8";
  try {
    if(mail($email,'MAU HRMS: '.$title,$body,$headers))return true;
    error_log('HRMS email notification failed for user '.$userId.'.');
  } catch(Throwable $error) {
    error_log('HRMS email notification failed for user '.$userId.': '.$error->getMessage());
  }
  return false;
}
function dispatch_notification_sms(?string $phone,string $title,string $message,int $userId): bool {
  if(SMS_GATEWAY_URL===''||SMS_GATEWAY_TOKEN===''){error_log('HRMS SMS notification skipped: configure SMS_GATEWAY_URL and SMS_GATEWAY_TOKEN.');return false;}
  if(!$phone){error_log('HRMS SMS notification skipped: no phone number for user '.$userId.'.');return false;}
  if(!filter_var(SMS_GATEWAY_URL,FILTER_VALIDATE_URL)||!function_exists('curl_init')){error_log('HRMS SMS notification failed: invalid gateway URL or PHP cURL extension unavailable.');return false;}
  $handle=curl_init(SMS_GATEWAY_URL);
  if($handle===false){error_log('HRMS SMS notification failed: could not initialize cURL.');return false;}
  $payload=json_encode(['from'=>SMS_SENDER_ID,'to'=>$phone,'message'=>'MAU HRMS: '.$title.'. '.$message],JSON_UNESCAPED_UNICODE);
  curl_setopt_array($handle,[
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>$payload,
    CURLOPT_HTTPHEADER=>['Authorization: Bearer '.SMS_GATEWAY_TOKEN,'Content-Type: application/json','Accept: application/json'],
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_CONNECTTIMEOUT=>5,
    CURLOPT_TIMEOUT=>10,
  ]);
  $response=curl_exec($handle);
  $status=(int)curl_getinfo($handle,CURLINFO_HTTP_CODE);
  $curlError=curl_error($handle);
  curl_close($handle);
  if($response!==false&&$status>=200&&$status<300)return true;
  error_log('HRMS SMS notification failed for user '.$userId.' (HTTP '.$status.'): '.($curlError!==''?$curlError:'gateway rejected the request.'));
  return false;
}
function persist_and_deliver_notification(int $id,string $message,array $details=[]): array {
  $schema=ensure_notifications_schema();
  $title=trim((string)($details['title']??'MAU HRMS notification'));
  $eventType=trim((string)($details['event_type']??'system'));
  unset($details['title'],$details['event_type']);
  $details['action']=$details['action']??$message;
  $details['date']=$details['date']??date(DATE_ATOM);
  $idColumn=$schema['id'];
  $typeColumn=$schema['type'];
  $stmt=db()->prepare("INSERT INTO notifications(user_id,title,`$typeColumn`,message,details_json) VALUES(?,?,?,?,?)");
  $stmt->execute([$id,$title,$eventType,$message,json_encode($details,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
  $recipient=db()->prepare("SELECT u.user_id,u.status,u.email AS account_email,u.phone AS account_phone,e.email AS employee_email,e.phone AS employee_phone FROM users u LEFT JOIN employees e ON e.emp_id=u.emp_id WHERE u.user_id=?");
  $recipient->execute([$id]);
  $row=$recipient->fetch();
  if(!$row||$row['status']!=='Active'){error_log('HRMS notification delivery skipped: user '.$id.' is missing or inactive.');return notification_delivery_defaults();}
  $email=filter_var($row['account_email'],FILTER_VALIDATE_EMAIL)?$row['account_email']:$row['employee_email'];
  $accountPhone=trim((string)($row['account_phone']??''));
  $phone=$accountPhone!==''?$accountPhone:trim((string)($row['employee_phone']??''));
  return [
    'email_sent'=>dispatch_notification_email(is_string($email)?$email:null,$title,$message,$details,$id),
    'sms_sent'=>dispatch_notification_sms($phone!==''?$phone:null,$title,$message,$id),
  ];
}
function dispatch_notifications(array $recipientUserIds,string $message,array $details=[]): array {
  $delivery=notification_delivery_defaults();
  foreach(array_unique(array_filter(array_map('intval',$recipientUserIds),static fn($id)=>$id>0)) as $recipientId) {
    try {
      $delivery=merge_notification_delivery($delivery,persist_and_deliver_notification($recipientId,$message,$details));
    } catch(Throwable $error) {
      error_log('HRMS notification dispatch failed for user '.$recipientId.': '.$error->getMessage());
    }
  }
  return $delivery;
}
function notify_event(array $recipients,string $message,array $details=[]): array {
  $userIds=array_map('intval',$recipients['user_ids']??[]);
  $roles=array_values(array_unique(array_filter($recipients['roles']??[],'is_string')));
  if($roles) {
    try {
      $placeholders=implode(',',array_fill(0,count($roles),'?'));
      $stmt=db()->prepare("SELECT user_id FROM users WHERE status='Active' AND role IN ($placeholders)");
      $stmt->execute($roles);
      foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $recipientId)$userIds[]=(int)$recipientId;
    } catch(Throwable $error) {
      error_log('HRMS role notification recipient lookup failed: '.$error->getMessage());
    }
  }
  return dispatch_notifications($userIds,$message,$details);
}
function notify_user(int $id,string $message,array $details=[]): array {
  return notify_event(['user_ids'=>[$id]],$message,$details);
}
function notify_roles(array $roles,string $message,array $details=[]): array {
  return notify_event(['roles'=>$roles],$message,$details);
}
function notify_employee(int $employeeId,string $message,array $details=[]): array {
  try {
    $stmt=db()->prepare("SELECT user_id FROM users WHERE emp_id=? AND status='Active'");
    $stmt->execute([$employeeId]);
    return dispatch_notifications(array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN)),$message,$details);
  } catch(Throwable $error) {
    error_log('HRMS employee notification recipient lookup failed: '.$error->getMessage());
    return notification_delivery_defaults();
  }
}
function notify_system_alert(array $recipientUserIds,string $title,string $message,array $details=[]): array {
  $details['title']=$title;
  $details['event_type']=$details['event_type']??'system_alert';
  $details['status']=$details['status']??'Informational';
  $details['action']=$details['action']??$message;
  return dispatch_notifications($recipientUserIds,$message,$details);
}
function json_input_required(array $d, array $keys): void { foreach($keys as $k) if(!isset($d[$k]) || $d[$k]==='') out(['error'=>"Missing required field: $k"],422); }
$method=$_SERVER['REQUEST_METHOD']; $path=trim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/');
// When the app is served from a subdirectory (such as /MAU-HRMS) or the built-in PHP server, strip the API script prefix.
$path=preg_replace('#^.+/api\.php/?#','',$path);
$path=preg_replace('#^api\.php/?#','',$path);
$parts=array_values(array_filter(explode('/',$path),fn($v)=>$v!==''));
$resource=$parts[0]??''; $id=isset($parts[1])?(int)$parts[1]:0; $d=body();
try {
if($resource==='colleges' && $method==='GET') {
  out(db()->query('SELECT college_id,name FROM colleges ORDER BY name')->fetchAll());
}
if($resource==='departments' && $method==='GET') {
  $collegeId=isset($_GET['college_id'])?filter_var($_GET['college_id'],FILTER_VALIDATE_INT):null;
  if(isset($_GET['college_id']) && ($collegeId===false || $collegeId<1))out(['error'=>'College ID must be a positive whole number'],422);
  $sql='SELECT d.dept_id AS department_id,d.dept_id,d.name,c.college_id,c.name AS college FROM departments d JOIN colleges c ON c.college_id=d.college_id';
  if($collegeId!==null) {
    $s=db()->prepare($sql.' WHERE d.college_id=? ORDER BY d.name');
    $s->execute([$collegeId]);
    out($s->fetchAll());
  }
  out(db()->query($sql.' ORDER BY c.name,d.name')->fetchAll());
}
if($resource==='auth' && $method==='POST' && ($parts[1]??'')==='register') {
  json_input_required($d,['full_name','username','password']);
  $full_name=trim((string)($d['full_name'] ?? ''));
  $username=trim((string)($d['username'] ?? ''));
  $password=(string)($d['password'] ?? '');
  if($full_name==='') out(['error'=>'Full name is required'],422);
  if($username==='') out(['error'=>'Username is required','translation_key'=>'usernameRequired'],422);
  $usernameError=username_policy_error($username);
  if($usernameError!==null)out(['error'=>'Username must be between 4 and 80 characters','translation_key'=>$usernameError],422);
  require_valid_password($password);
  $requestedRole=trim((string)($d['role']??'Employee'));
  $roles=['Administrator','HR Officer','Department Head','Employee','Management'];
  if(!in_array($requestedRole,$roles,true)) out(['error'=>'Invalid requested role'],422);
  $sex=isset($d['sex']) && trim((string)$d['sex'])!=='' ? trim((string)$d['sex']) : null;
  if($sex!==null && !in_array($sex,['Male','Female','Other'],true))out(['error'=>'Sex must be Male, Female, or Other'],422);
  $birthDate=isset($d['birth_date']) ? trim((string)$d['birth_date']) : '';
  if($birthDate!=='' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$birthDate) || !checkdate((int)substr($birthDate,5,2),(int)substr($birthDate,8,2),(int)substr($birthDate,0,4))))out(['error'=>'Birth date must be a valid date in YYYY-MM-DD format'],422);
  if($birthDate==='')$birthDate=null;
  $collegeValue=$d['college_id']??'';
  $departmentValue=$d['department_id']??($d['dept_id']??'');
  $collegeId=null;
  $deptId=null;
  if(trim((string)$collegeValue)!=='' || trim((string)$departmentValue)!=='') {
    $collegeId=filter_var($collegeValue,FILTER_VALIDATE_INT);
    $deptId=filter_var($departmentValue,FILTER_VALIDATE_INT);
    if($collegeId===false || $collegeId<1)out(['error'=>'Select a valid college'],422);
    if($deptId===false || $deptId<1)out(['error'=>'Select a department for the chosen college'],422);
    $departmentStmt=db()->prepare('SELECT 1 FROM departments WHERE dept_id=? AND college_id=?');
    $departmentStmt->execute([$deptId,$collegeId]);
    if(!$departmentStmt->fetchColumn())out(['error'=>'Department does not belong to the selected college'],422);
  }
  $staffNo=isset($d['staff_no'])?trim((string)$d['staff_no']):'';
  $role='Employee';
  $pdo=db();
  try {
    $pdo->beginTransaction();
    $empStmt=$pdo->prepare('INSERT INTO employees(staff_no,full_name,sex,birth_date,phone,email,position,college_id,dept_id,hire_date,salary,annual_leave_balance) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
    $empStmt->execute([
      $staffNo!=='' ? $staffNo : null,
      $full_name,
      $sex,
      $birthDate,
      isset($d['phone']) && trim((string)$d['phone'])!=='' ? trim((string)$d['phone']) : null,
      isset($d['email']) && trim((string)$d['email'])!=='' ? trim((string)$d['email']) : null,
      isset($d['position']) && trim((string)$d['position'])!=='' ? trim((string)$d['position']) : null,
      $collegeId,
      $deptId,
      $d['hire_date'] ?? null,
      max(0,(float)($d['salary'] ?? 0)),
      max(0,(float)($d['annual_leave_balance'] ?? 20))
    ]);
    $empId=(int)$pdo->lastInsertId();
    $userStmt=$pdo->prepare('INSERT INTO users(username,password_hash,role,status,emp_id) VALUES(?,?,?,\'Active\',?)');
    $userStmt->execute([$username,password_hash($password,PASSWORD_DEFAULT),$role,$empId]);
    $userId=(int)$pdo->lastInsertId();
    if($requestedRole!=='Employee') {
      $requestStmt=$pdo->prepare("INSERT INTO role_requests(user_id,requested_role) VALUES(?,?)");
      $requestStmt->execute([$userId,$requestedRole]);
    }
    $pdo->commit();
    if($requestedRole!=='Employee'){
      notify_roles(['Administrator'],'A role request is awaiting review.',[
        'title'=>'Role request submitted',
        'event_type'=>'role_request',
        'request_type'=>'Role request',
        'requested_role'=>$requestedRole,
        'status'=>'Pending',
        'sender'=>$full_name,
        'action'=>$full_name.' requested the '.$requestedRole.' role.',
      ]);
    }
    notify_user($userId,'Your MAU HRMS employee account was created successfully.',[
      'title'=>'Account created',
      'event_type'=>'account_created',
      'request_type'=>'Employee account',
      'status'=>'Active',
      'sender'=>'MAU HRMS',
      'action'=>'Sign in with the username and password you chose during registration.',
    ]);
    out(['ok'=>true,'message'=>$requestedRole==='Employee'?'Employee account created successfully':'Employee account created. Your requested role is pending administrator approval.'],201);
  } catch(Throwable $e) {
    if($pdo->inTransaction())$pdo->rollBack();
    if(str_contains($e->getMessage(),'Duplicate entry')) out(['error'=>'Username or staff number already exists'],409);
    error_log($e->getMessage());
    out(['error'=>'Unable to create employee account'],422);
  }
}
if($resource==='auth' && $method==='POST' && ($parts[1]??'')==='login') {
  json_input_required($d,['username','password']);
  $loginUsername=trim((string)$d['username']);
  $loginPassword=is_string($d['password'])?$d['password']:'';
  if($loginUsername==='')out(['error'=>'Username cannot be empty','translation_key'=>'usernameRequired'],422);
  $usernameError=username_policy_error($loginUsername);
  if($usernameError!==null)out(['error'=>'Username must be at least 4 characters long','translation_key'=>$usernameError],422);
  $s=db()->prepare("SELECT u.user_id,u.username,u.password_hash,u.role,u.status,u.emp_id,u.failed_attempts,u.locked_until,COALESCE(e.full_name,u.username) AS full_name FROM users u LEFT JOIN employees e ON e.emp_id=u.emp_id WHERE u.username=? LIMIT 1");
  $s->execute([$loginUsername]);
  $u=$s->fetch();
  if(!$u || $u['status']!=='Active') {
    out(['error'=>'Invalid credentials or account temporarily locked'],401);
  }
  if($u['locked_until'] && strtotime($u['locked_until'])>time()) {
    out(['error'=>'Invalid credentials or account temporarily locked'],401);
  }
  if(!password_verify($loginPassword,$u['password_hash'])) {
    $attempt=min(255,(int)$u['failed_attempts']+1);
    $locked=$attempt>=5?date('Y-m-d H:i:s',time()+900):null;
    db()->prepare('UPDATE users SET failed_attempts=?,locked_until=? WHERE user_id=?')->execute([$attempt,$locked,$u['user_id']]);
    out(['error'=>'Invalid credentials or account temporarily locked'],401);
  }
  $newHash=password_needs_rehash($u['password_hash'],PASSWORD_DEFAULT)?password_hash($loginPassword,PASSWORD_DEFAULT):$u['password_hash'];
  db()->prepare('UPDATE users SET password_hash=?,failed_attempts=0,locked_until=NULL WHERE user_id=?')->execute([$newHash,$u['user_id']]);
  session_regenerate_id(true);
  $_SESSION['user']=[
    'user_id'=>(int)$u['user_id'],
    'username'=>$u['username'],
    'role'=>$u['role'],
    'emp_id'=>$u['emp_id']!==null?(int)$u['emp_id']:null,
    'full_name'=>$u['full_name'],
  ];
  log_action('LOGIN');
  out(['user'=>$_SESSION['user']]);
}
 if($resource==='auth' && ($parts[1]??'')==='me' && $method==='GET') { if(empty($_SESSION['user']))out(['user'=>null]);out(['user'=>require_login()]); }
 if($resource==='auth' && ($parts[1]??'')==='logout' && $method==='POST') { if(isset($_SESSION['user']['user_id'])){$s=db()->prepare('SELECT 1 FROM users WHERE user_id=?');$s->execute([(int)$_SESSION['user']['user_id']]);if($s->fetchColumn())log_action('LOGOUT');} $_SESSION=[]; session_destroy(); out(['ok'=>true]); }
 if($resource==='users' && !empty($_SESSION['user']['user_id']))ensure_user_accounts_schema();
 $user=require_login();
 if($resource==='profile' && $method==='GET') {
   $s=db()->prepare('SELECT u.user_id,u.username,u.emp_id,COALESCE(e.full_name,u.username) AS full_name,e.phone,e.email,e.sex,e.birth_date,e.position FROM users u LEFT JOIN employees e ON e.emp_id=u.emp_id WHERE u.user_id=?');
   $s->execute([(int)$user['user_id']]);
   $profile=$s->fetch();
   if(!$profile)out(['error'=>'User account not found'],404);
   out($profile);
 }
 if($resource==='profile' && $method==='PATCH') {
   if(array_key_exists('username',$d)&&!is_scalar($d['username']))out(['error'=>'Username cannot be empty','translation_key'=>'usernameRequired'],422);
   $username=array_key_exists('username',$d)?trim((string)$d['username']):null;
   if($username!==null && $username==='')out(['error'=>'Username cannot be empty','translation_key'=>'usernameRequired'],422);
   if($username!==null) {
     $usernameError=username_policy_error($username);
     if($usernameError!==null)out(['error'=>'Username must be between 4 and 80 characters','translation_key'=>$usernameError],422);
   }
   $employeeFields=['full_name','phone','email','sex','birth_date'];
   $changes=[];
   foreach($employeeFields as $field) {
     if(!array_key_exists($field,$d))continue;
     if(!is_scalar($d[$field]) && $d[$field]!==null)out(['error'=>"Invalid profile value: $field"],422);
     $value=trim((string)($d[$field]??''));
     if($field==='full_name') {
       if($value==='')out(['error'=>'Full name cannot be empty','translation_key'=>'fullNameRequired'],422);
       if(strlen($value)>160)out(['error'=>'Full name must be 160 characters or fewer','translation_key'=>'fullNameTooLong'],422);
     } elseif($field==='phone' && strlen($value)>40)out(['error'=>'Phone number must be 40 characters or fewer','translation_key'=>'phoneTooLong'],422);
     elseif($field==='email' && $value!=='' && (!filter_var($value,FILTER_VALIDATE_EMAIL)||strlen($value)>160))out(['error'=>'Enter a valid email address','translation_key'=>'invalidEmail'],422);
     elseif($field==='sex' && $value!=='' && !in_array($value,['Male','Female','Other'],true))out(['error'=>'Sex must be Male, Female, or Other','translation_key'=>'invalidSex'],422);
     elseif($field==='birth_date' && $value!=='' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value)||!checkdate((int)substr($value,5,2),(int)substr($value,8,2),(int)substr($value,0,4))))out(['error'=>'Birth date must be a valid date in YYYY-MM-DD format','translation_key'=>'invalidBirthDate'],422);
     $changes[$field]=in_array($field,['phone','email','sex','birth_date'],true)&&$value===''?null:$value;
   }
   if($username===null && !$changes)out(['error'=>'Provide at least one profile field to update','translation_key'=>'profileFieldRequired'],422);
   if($changes && empty($user['emp_id']))out(['error'=>'This account is not linked to an employee','translation_key'=>'employeeNotLinked'],422);
   $pdo=db();
   $pdo->beginTransaction();
   try {
     if($username!==null)$pdo->prepare('UPDATE users SET username=? WHERE user_id=?')->execute([$username,(int)$user['user_id']]);
     if($changes) {
       $sets=[];$values=[];
       foreach($changes as $field=>$value){$sets[]="`$field`=?";$values[]=$value;}
       $values[]=(int)$user['emp_id'];
       $pdo->prepare('UPDATE employees SET '.implode(',',$sets).' WHERE emp_id=?')->execute($values);
     }
     $pdo->commit();
   } catch(Throwable $e) {
     if($pdo->inTransaction())$pdo->rollBack();
     if($e instanceof PDOException && (string)$e->getCode()==='23000')out(['error'=>'Username already exists','translation_key'=>'user_already_exists'],409);
     throw $e;
   }
   if($username!==null)$_SESSION['user']['username']=$username;
   if(isset($changes['full_name']))$_SESSION['user']['full_name']=$changes['full_name'];
   log_action('UPDATE_PROFILE');
   if($changes)notify_roles(['Administrator','HR Officer'],'An employee updated their profile information.',[
     'title'=>'Employee profile updated',
     'event_type'=>'profile_updated',
     'request_type'=>'Employee profile',
     'employee_id'=>(int)$user['emp_id'],
     'status'=>'Updated',
     'sender'=>$_SESSION['user']['full_name']??$user['full_name'],
     'changes'=>$changes,
     'action'=>'Review the employee profile if follow-up is needed.',
   ]);
   $s=$pdo->prepare('SELECT u.user_id,u.username,u.emp_id,COALESCE(e.full_name,u.username) AS full_name,e.phone,e.email,e.sex,e.birth_date,e.position FROM users u LEFT JOIN employees e ON e.emp_id=u.emp_id WHERE u.user_id=?');
   $s->execute([(int)$user['user_id']]);
   out($s->fetch());
 }
 if($resource==='change-password' && $method==='POST') {
   json_input_required($d,['current_password','new_password','confirm_password']);
   $currentPassword=is_string($d['current_password'])?$d['current_password']:'';
   $newPassword=is_string($d['new_password'])?$d['new_password']:'';
   $confirmPassword=is_string($d['confirm_password'])?$d['confirm_password']:'';
   require_valid_password($newPassword);
   if($newPassword!==$confirmPassword)out(['error'=>'New password confirmation does not match','translation_key'=>'passwordMismatch'],422);
   $s=db()->prepare('SELECT password_hash FROM users WHERE user_id=?');
   $s->execute([(int)$user['user_id']]);
   $passwordHash=$s->fetchColumn();
   if(!$passwordHash||!password_verify($currentPassword,$passwordHash))out(['error'=>'Current password is incorrect','translation_key'=>'currentPasswordIncorrect'],422);
   db()->prepare('UPDATE users SET password_hash=?,failed_attempts=0,locked_until=NULL WHERE user_id=?')->execute([password_hash($newPassword,PASSWORD_DEFAULT),(int)$user['user_id']]);
   log_action('CHANGE_PASSWORD');
   session_regenerate_id(true);
   out(['ok'=>true]);
 }
 if($resource==='colleges') {
   require_role(['Administrator']);
   if($method==='POST') {
     json_input_required($d,['name']);
     $name=trim((string)$d['name']);
     if($name==='')out(['error'=>'College name is required'],422);
     $s=db()->prepare('INSERT INTO colleges(name) VALUES(?)');
     $s->execute([$name]);
     log_action('CREATE_COLLEGE',$name);
     notify_roles(['Administrator','HR Officer'],'A college was added to the HRMS.',[
       'title'=>'College created',
       'event_type'=>'college_created',
       'request_type'=>'College',
       'college'=>$name,
       'status'=>'Created',
       'sender'=>$user['full_name'],
       'action'=>'A new college record is available in the system.',
     ]);
     out(['college_id'=>(int)db()->lastInsertId()],201);
   }
   if($method==='PUT' && $id) {
     json_input_required($d,['name']);
     $name=trim((string)$d['name']);if($name==='')out(['error'=>'College name is required'],422);
     $s=db()->prepare('UPDATE colleges SET name=? WHERE college_id=?');
     $s->execute([$name,$id]);
     if(!$s->rowCount() && !db()->query('SELECT 1 FROM colleges WHERE college_id='.(int)$id)->fetchColumn())out(['error'=>'College not found'],404);
     log_action('UPDATE_COLLEGE',(string)$id);
     out(['ok'=>true]);
   }
   if($method==='DELETE' && $id) {
     try {
       $s=db()->prepare('DELETE FROM colleges WHERE college_id=?');
       $s->execute([$id]);
     } catch(PDOException $e) {
       if((string)$e->getCode()==='23000')out(['error'=>'Remove or reassign this college’s departments before deleting it'],409);
       throw $e;
     }
     if(!$s->rowCount())out(['error'=>'College not found'],404);
     log_action('DELETE_COLLEGE',(string)$id);
     out(['ok'=>true]);
   }
 }
 if($resource==='users') {
   require_role(['Administrator']);
   $roles=['Administrator','HR Officer','Department Head','Employee','Management'];
   if($method==='GET')out(db()->query('SELECT u.user_id,u.username,u.role,u.status,u.email,u.phone,u.emp_id,e.full_name,e.staff_no FROM users u LEFT JOIN employees e ON e.emp_id=u.emp_id ORDER BY u.user_id DESC')->fetchAll());
   if($method==='POST') {
     json_input_required($d,['username','password','role']);
     $username=trim((string)$d['username']);
     $email=trim((string)($d['email']??''));
     $phone=trim((string)($d['phone']??''));
     $password=(string)$d['password'];
     if($username==='')out(['error'=>'Username is required','translation_key'=>'usernameRequired'],422);
     $usernameError=username_policy_error($username);
     if($usernameError!==null)out(['error'=>'Username must be between 4 and 80 characters','translation_key'=>$usernameError],422);
     if($email!==''&&(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>160))out(['error'=>'Enter a valid email address'],422);
     if(strlen($phone)>40)out(['error'=>'Phone number must be 40 characters or fewer'],422);
     require_valid_password((string)$password);
     if(!in_array($d['role'],$roles,true))out(['error'=>'Invalid account role'],422);
     $status=$d['status']??'Active';
     if(!in_array($status,['Active','Inactive'],true))out(['error'=>'Invalid account status'],422);
     $empId=isset($d['emp_id'])&&$d['emp_id']!==''?filter_var($d['emp_id'],FILTER_VALIDATE_INT):null;
     if($empId===false || ($empId!==null && $empId<1))out(['error'=>'Select a valid employee'],422);
     if($empId!==null){$s=db()->prepare('SELECT 1 FROM employees WHERE emp_id=?');$s->execute([$empId]);if(!$s->fetchColumn())out(['error'=>'Employee not found'],404);}
     try{$s=db()->prepare('INSERT INTO users(username,password_hash,role,status,email,phone,emp_id) VALUES(?,?,?,?,?,?,?)');$s->execute([$username,password_hash($password,PASSWORD_DEFAULT),$d['role'],$status,$email!==''?$email:null,$phone!==''?$phone:null,$empId]);}
     catch(PDOException $e){if((string)$e->getCode()==='23000')out(['error'=>'Username or employee already has an account'],409);throw $e;}
     log_action('CREATE_USER',$username);
     $newUserId=(int)db()->lastInsertId();
     notify_user($newUserId,'Your MAU HRMS account has been created.',[
       'title'=>'Account created',
       'event_type'=>'account_created',
       'request_type'=>'User account',
       'status'=>$status,
       'sender'=>$user['full_name'],
       'username'=>$username,
       'role'=>$d['role'],
       'action'=>'An administrator created your MAU HRMS account. Contact the administrator for your sign-in credentials.',
     ]);
     out(['user_id'=>$newUserId],201);
   }
   if($method==='PATCH' && $id) {
     $s=db()->prepare('SELECT user_id,username,role,status,email,phone,emp_id FROM users WHERE user_id=?');
     $s->execute([$id]);$account=$s->fetch();
     if(!$account)out(['error'=>'User account not found'],404);
     $username=trim((string)($d['username']??$account['username']));
     $email=array_key_exists('email',$d)?trim((string)$d['email']):(string)($account['email']??'');
     $phone=array_key_exists('phone',$d)?trim((string)$d['phone']):(string)($account['phone']??'');
     $role=$d['role']??$account['role'];
     $status=$d['status']??$account['status'];
     if($username==='')out(['error'=>'Username is required','translation_key'=>'usernameRequired'],422);
     $usernameError=username_policy_error($username);
     if($usernameError!==null)out(['error'=>'Username must be between 4 and 80 characters','translation_key'=>$usernameError],422);
     if($email!==''&&(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>160))out(['error'=>'Enter a valid email address'],422);
     if(strlen($phone)>40)out(['error'=>'Phone number must be 40 characters or fewer'],422);
     if(!in_array($role,$roles,true))out(['error'=>'Invalid account role'],422);
     if(!in_array($status,['Active','Inactive'],true))out(['error'=>'Invalid account status'],422);
     if((int)$account['user_id']===(int)$user['user_id'] && ($status!=='Active'||$role!=='Administrator'))out(['error'=>'You cannot deactivate or demote your own administrator account'],422);
     $empId=array_key_exists('emp_id',$d)?($d['emp_id']===''||$d['emp_id']===null?null:filter_var($d['emp_id'],FILTER_VALIDATE_INT)):$account['emp_id'];
     if($empId===false || ($empId!==null && (int)$empId<1))out(['error'=>'Select a valid employee'],422);
     if($empId!==null){$s=db()->prepare('SELECT 1 FROM employees WHERE emp_id=?');$s->execute([$empId]);if(!$s->fetchColumn())out(['error'=>'Employee not found'],404);}
     $password=array_key_exists('password',$d)?(string)$d['password']:'';
     if($password!=='')require_valid_password($password);
     try {
       if($password!=='')$s=db()->prepare('UPDATE users SET username=?,role=?,status=?,email=?,phone=?,emp_id=?,password_hash=? WHERE user_id=?')->execute([$username,$role,$status,$email!==''?$email:null,$phone!==''?$phone:null,$empId,password_hash($password,PASSWORD_DEFAULT),$id]);
       else $s=db()->prepare('UPDATE users SET username=?,role=?,status=?,email=?,phone=?,emp_id=? WHERE user_id=?')->execute([$username,$role,$status,$email!==''?$email:null,$phone!==''?$phone:null,$empId,$id]);
     } catch(PDOException $e){if((string)$e->getCode()==='23000')out(['error'=>'Username or employee already has an account'],409);throw $e;}
     log_action('UPDATE_USER',(string)$id);
     notify_user((int)$account['user_id'],'Your MAU HRMS account settings were updated.',[
       'title'=>'Account updated',
       'event_type'=>'account_updated',
       'request_type'=>'User account',
       'status'=>$status,
       'sender'=>$user['full_name'],
       'username'=>$username,
       'role'=>$role,
       'action'=>'An administrator updated your account settings. Contact the administrator if you did not expect this change.',
     ]);
     out(['ok'=>true]);
   }
   if($method==='DELETE' && $id) {
     if($id===(int)$user['user_id'])out(['error'=>'You cannot delete your own account'],422);
     $s=db()->prepare('DELETE FROM users WHERE user_id=?');$s->execute([$id]);
     if(!$s->rowCount())out(['error'=>'User account not found'],404);
     log_action('DELETE_USER',(string)$id);
     out(['ok'=>true]);
   }
 }
 if($resource==='profile-requests') {
   if($method==='GET') {
     require_role(['Administrator','HR Officer','Employee']);
     $sql='SELECT p.request_id,p.emp_id,p.user_id,p.changes_json,p.status,p.created_at,p.reviewed_at,e.full_name,e.staff_no,u.username FROM profile_requests p JOIN employees e ON e.emp_id=p.emp_id JOIN users u ON u.user_id=p.user_id';
     $args=[];
     if($user['role']==='Employee'){$sql.=' WHERE p.user_id=?';$args[]=$user['user_id'];}
     $sql.=' ORDER BY (p.status=\'Pending\') DESC,p.created_at DESC';
     $s=db()->prepare($sql);$s->execute($args);$records=$s->fetchAll();
     foreach($records as &$record)$record['changes']=json_decode($record['changes_json'],true)?:[];
     out($records);
   }
   if($method==='POST') {
     require_role(['Employee']);
     if(empty($user['emp_id']))out(['error'=>'This account is not linked to an employee'],422);
     $changes=$d['changes']??null;
     if(!is_array($changes)||!$changes)out(['error'=>'Provide profile fields to request'],422);
     $allowed=['full_name','phone','email','position','college_id','department_id'];
     $changes=array_intersect_key($changes,array_flip($allowed));
     if(!$changes)out(['error'=>'No supported profile fields were provided'],422);
     foreach($changes as $key=>$value){if(!is_scalar($value))out(['error'=>"Invalid profile value: $key"],422);$changes[$key]=is_string($value)?trim($value):$value;}
     if(isset($changes['full_name'])&&trim((string)$changes['full_name'])==='')out(['error'=>'Full name cannot be empty'],422);
     if(isset($changes['email'])&&$changes['email']!==''&&!filter_var($changes['email'],FILTER_VALIDATE_EMAIL))out(['error'=>'Enter a valid email address'],422);
     if(isset($changes['college_id'])||isset($changes['department_id'])) {
       $collegeId=filter_var($changes['college_id']??false,FILTER_VALIDATE_INT);
       $deptId=filter_var($changes['department_id']??false,FILTER_VALIDATE_INT);
       if($collegeId===false||$collegeId<1||$deptId===false||$deptId<1)out(['error'=>'Select both a valid college and department'],422);
       $s=db()->prepare('SELECT 1 FROM departments WHERE dept_id=? AND college_id=?');$s->execute([$deptId,$collegeId]);
       if(!$s->fetchColumn())out(['error'=>'Department does not belong to the selected college'],422);
       $changes['college_id']=$collegeId;$changes['dept_id']=$deptId;unset($changes['department_id']);
     }
     $pdo=db();$pdo->beginTransaction();
     try {
       $s=$pdo->prepare("INSERT INTO profile_requests(emp_id,user_id,changes_json) VALUES(?,?,?)");
       $s->execute([(int)$user['emp_id'],(int)$user['user_id'],json_encode($changes,JSON_UNESCAPED_UNICODE)]);
       $requestId=(int)$pdo->lastInsertId();$pdo->commit();
     } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
     log_action('REQUEST_PROFILE_UPDATE',(string)$requestId);
     $delivery=notify_roles(['Administrator','HR Officer'],'An employee profile update request is awaiting review.',[
       'title'=>'Profile update request',
       'event_type'=>'profile_request',
       'request_type'=>'Profile update',
       'request_id'=>$requestId,
       'status'=>'Pending',
       'sender'=>$user['full_name'],
       'changes'=>$changes,
       'action'=>'Review the employee profile update request.',
     ]);
     out(['request_id'=>$requestId,'email_notification_sent'=>$delivery['email_sent'],'sms_notification_sent'=>$delivery['sms_sent']],201);
   }
   if($method==='PATCH' && $id) {
     require_role(['Administrator','HR Officer']);
     $decision=$d['decision']??'';
     if(!in_array($decision,['approve','reject'],true))out(['error'=>'Decision must be approve or reject'],422);
     $pdo=db();$pdo->beginTransaction();
     try {
       $s=$pdo->prepare('SELECT * FROM profile_requests WHERE request_id=? FOR UPDATE');$s->execute([$id]);$request=$s->fetch();
       if(!$request){$pdo->rollBack();out(['error'=>'Profile request not found'],404);}
       if($request['status']!=='Pending'){$pdo->rollBack();out(['error'=>'This request has already been reviewed'],409);}
       $changes=json_decode($request['changes_json'],true);
       if(!is_array($changes))throw new RuntimeException('Stored profile request data is invalid');
       if($decision==='approve') {
         $columns=['full_name','phone','email','position','college_id','dept_id'];
         $updates=[];$values=[];
         foreach($columns as $column)if(array_key_exists($column,$changes)){$updates[]="`$column`=?";$values[]=$changes[$column];}
         if($updates){$values[]=$request['emp_id'];$pdo->prepare('UPDATE employees SET '.implode(',',$updates).' WHERE emp_id=?')->execute($values);}
       }
       $status=$decision==='approve'?'Approved':'Rejected';
       $pdo->prepare('UPDATE profile_requests SET status=?,reviewed_by=?,reviewed_at=NOW() WHERE request_id=?')->execute([$status,$user['user_id'],$id]);
       $pdo->commit();
     } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
     log_action('REVIEW_PROFILE_REQUEST',$status.' request '.$id);
     notify_user((int)$request['user_id'],'Your profile update request was '.strtolower($status).'.',[
       'title'=>'Profile update '.$status,
       'event_type'=>'profile_request',
       'request_type'=>'Profile update',
       'request_id'=>$id,
       'status'=>$status,
       'sender'=>$user['full_name'],
       'changes'=>$changes,
       'action'=>'Your profile update request was '.strtolower($status).'.',
     ]);
     out(['ok'=>true,'status'=>$status]);
   }
 }
 if($resource==='employee-accounts' && $method==='POST') {
   require_role(['Administrator']);
   json_input_required($d,['emp_id','username','password','role']);
   $empId=filter_var($d['emp_id'],FILTER_VALIDATE_INT);
   $username=trim((string)$d['username']);
   $roles=['Administrator','HR Officer','Department Head','Employee','Management'];
   if($empId===false || $empId<1)out(['error'=>'Select a valid employee'],422);
   if($username==='')out(['error'=>'Username is required','translation_key'=>'usernameRequired'],422);
   $usernameError=username_policy_error($username);
   if($usernameError!==null)out(['error'=>'Username must be between 4 and 80 characters','translation_key'=>$usernameError],422);
   require_valid_password((string)$d['password']);
   if(!in_array($d['role'],$roles,true))out(['error'=>'Invalid account role'],422);
   $employee=db()->prepare('SELECT emp_id,full_name FROM employees WHERE emp_id=?');
   $employee->execute([$empId]);
   $employeeRecord=$employee->fetch();
   if(!$employeeRecord)out(['error'=>'Employee not found'],404);
   $existing=db()->prepare('SELECT user_id FROM users WHERE emp_id=?');
   $existing->execute([$empId]);
   if($existing->fetchColumn())out(['error'=>'This employee already has a login account'],409);
   $account=db()->prepare("INSERT INTO users(username,password_hash,role,status,emp_id) VALUES(?,?,?,'Active',?)");
   $account->execute([$username,password_hash((string)$d['password'],PASSWORD_DEFAULT),$d['role'],$empId]);
   log_action('CREATE_EMPLOYEE_ACCOUNT',(string)$empId);
   notify_employee($empId,'Your MAU HRMS login account has been created.',[
     'title'=>'Employee account created',
     'event_type'=>'account_created',
     'request_type'=>'Employee account',
     'employee_id'=>$empId,
     'employee_name'=>$employeeRecord['full_name'],
     'username'=>$username,
     'role'=>$d['role'],
     'status'=>'Active',
     'sender'=>$user['full_name'],
     'action'=>'An administrator created your sign-in account. Contact the administrator for your credentials.',
   ]);
   out(['ok'=>true],201);
 }
 if($resource==='role-requests') {
   require_role(['Administrator']);
   if($method==='GET') {
     $s=db()->query("SELECT r.request_id,r.requested_role,r.status,r.created_at,u.username,e.full_name,e.staff_no FROM role_requests r JOIN users u ON u.user_id=r.user_id LEFT JOIN employees e ON e.emp_id=u.emp_id ORDER BY (r.status='Pending') DESC,r.created_at DESC");
     out($s->fetchAll());
   }
   if($method==='PATCH' && $id) {
     $decision=$d['decision']??'';
     if(!in_array($decision,['approve','reject'],true))out(['error'=>'Decision must be approve or reject'],422);
     $pdo=db();
     $pdo->beginTransaction();
     try {
       $s=$pdo->prepare('SELECT r.user_id,r.requested_role,r.status,u.username,COALESCE(e.full_name,u.username) AS full_name FROM role_requests r JOIN users u ON u.user_id=r.user_id LEFT JOIN employees e ON e.emp_id=u.emp_id WHERE r.request_id=? FOR UPDATE');
       $s->execute([$id]);
       $request=$s->fetch();
       if(!$request) { $pdo->rollBack(); out(['error'=>'Role request not found'],404); }
       if($request['status']!=='Pending') { $pdo->rollBack(); out(['error'=>'This role request has already been reviewed'],409); }
       $status=$decision==='approve'?'Approved':'Rejected';
       $pdo->prepare('UPDATE role_requests SET status=?,reviewed_by=?,reviewed_at=NOW() WHERE request_id=?')->execute([$status,$user['user_id'],$id]);
       if($decision==='approve')$pdo->prepare('UPDATE users SET role=? WHERE user_id=?')->execute([$request['requested_role'],$request['user_id']]);
       $pdo->commit();
     } catch(Throwable $e) {
       if($pdo->inTransaction())$pdo->rollBack();
       throw $e;
     }
     log_action('REVIEW_ROLE_REQUEST',$status.' request '.$id);
     notify_user((int)$request['user_id'],'Your role request was '.strtolower($status).'.',[
       'title'=>'Role request '.$status,
       'event_type'=>'role_request',
       'request_type'=>'Role request',
       'requested_role'=>$request['requested_role'],
       'status'=>$status,
       'sender'=>$user['full_name'],
       'action'=>'Your request for the '.$request['requested_role'].' role was '.strtolower($status).'.',
     ]);
     out(['ok'=>true,'status'=>$status]);
   }
 }
 if($resource==='dashboard' && $method==='GET') {
   $pdo=db(); $counts=[]; foreach(['employees','departments','vacancies','leave_requests','attendance'] as $t) $counts[$t]=(int)$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
   $counts['pending_leave']=(int)$pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status IN ('Pending Head','Pending HR')")->fetchColumn();
   out(['counts'=>$counts]);
 }
 if($resource==='departments') {
   require_role(['Administrator','HR Officer']);
   if($method==='POST'){
     json_input_required($d,['name','college_id']);
     $name=trim((string)$d['name']);if($name==='')out(['error'=>'Department name is required'],422);
     $collegeId=filter_var($d['college_id'],FILTER_VALIDATE_INT);
     if($collegeId===false || $collegeId<1)out(['error'=>'Select a valid college'],422);
     $college=db()->prepare('SELECT 1 FROM colleges WHERE college_id=?');
     $college->execute([$collegeId]);
     if(!$college->fetchColumn())out(['error'=>'College not found'],404);
     $s=db()->prepare('INSERT INTO departments(name,college_id) VALUES(?,?)');
     $s->execute([$name,$collegeId]);
     log_action('CREATE_DEPARTMENT',$name);
     notify_roles(['Administrator','HR Officer'],'A department was added to the HRMS.',[
       'title'=>'Department created',
       'event_type'=>'department_created',
       'request_type'=>'Department',
       'department'=>$name,
       'college_id'=>$collegeId,
       'status'=>'Created',
       'sender'=>$user['full_name'],
       'action'=>'A new department record is available in the system.',
     ]);
     out(['dept_id'=>(int)db()->lastInsertId()],201);
   }
   if($method==='PUT' && $id) {
     json_input_required($d,['name','college_id']);
     $collegeId=filter_var($d['college_id'],FILTER_VALIDATE_INT);
     $name=trim((string)$d['name']);
     if($collegeId===false||$collegeId<1)out(['error'=>'Select a valid college'],422);
     if($name==='')out(['error'=>'Department name is required'],422);
     $s=db()->prepare('SELECT 1 FROM colleges WHERE college_id=?');$s->execute([$collegeId]);
     if(!$s->fetchColumn())out(['error'=>'College not found'],404);
     $s=db()->prepare('SELECT college_id FROM departments WHERE dept_id=?');$s->execute([$id]);$currentCollege=$s->fetchColumn();
     if($currentCollege===false)out(['error'=>'Department not found'],404);
     if((int)$currentCollege!==$collegeId){$s=db()->prepare('SELECT 1 FROM employees WHERE dept_id=? LIMIT 1');$s->execute([$id]);if($s->fetchColumn())out(['error'=>'Reassign employees before moving this department to another college'],409);}
     $s=db()->prepare('UPDATE departments SET name=?,college_id=? WHERE dept_id=?');
     $s->execute([$name,$collegeId,$id]);
     if(!$s->rowCount()){$s=db()->prepare('SELECT 1 FROM departments WHERE dept_id=?');$s->execute([$id]);if(!$s->fetchColumn())out(['error'=>'Department not found'],404);}
     log_action('UPDATE_DEPARTMENT',(string)$id);
     notify_roles(['Administrator','HR Officer'],'A department record was updated.',[
       'title'=>'Department updated',
       'event_type'=>'department_updated',
       'request_type'=>'Department',
       'department_id'=>$id,
       'department'=>$name,
       'college_id'=>$collegeId,
       'status'=>'Updated',
       'sender'=>$user['full_name'],
       'action'=>'A department record was updated in the HRMS.',
     ]);
     out(['ok'=>true]);
   }
   if($method==='DELETE' && $id) {
     $s=db()->prepare('SELECT 1 FROM employees WHERE dept_id=? LIMIT 1');$s->execute([$id]);
     if($s->fetchColumn())out(['error'=>'Reassign employees before deleting this department'],409);
     $s=db()->prepare('DELETE FROM departments WHERE dept_id=?');$s->execute([$id]);
     if(!$s->rowCount())out(['error'=>'Department not found'],404);
     log_action('DELETE_DEPARTMENT',(string)$id);
     out(['ok'=>true]);
   }
 }
 if($resource==='employees') {
  if($method==='GET') { if($user['role']==='Employee'){ $s=db()->prepare('SELECT e.*,d.name department FROM employees e LEFT JOIN departments d ON d.dept_id=e.dept_id WHERE e.emp_id=?');$s->execute([$user['emp_id']]);out($s->fetch()?:[]); } require_role(['Administrator','HR Officer']);$q=trim($_GET['q']??'');$sql='SELECT e.*,d.name department FROM employees e LEFT JOIN departments d ON d.dept_id=e.dept_id';$args=[];if($q!==''){$sql.=' WHERE e.full_name LIKE ? OR e.staff_no LIKE ? OR e.email LIKE ?';$args=["%$q%","%$q%","%$q%"];}$sql.=' ORDER BY e.emp_id DESC';$s=db()->prepare($sql);$s->execute($args);out($s->fetchAll()); }
   require_role(['Administrator','HR Officer']);
   if($method==='POST'){
     json_input_required($d,['full_name']);
     $hasAccount=trim((string)($d['account_username']??''))!=='' || trim((string)($d['account_password']??''))!=='';
     if($hasAccount){
       require_role(['Administrator']);
       json_input_required($d,['account_username','account_password','account_role']);
       $d['account_username']=trim((string)$d['account_username']);
       if($d['account_username']==='')out(['error'=>'Login username is required','translation_key'=>'usernameRequired'],422);
       $usernameError=username_policy_error($d['account_username']);
       if($usernameError!==null)out(['error'=>'Username must be between 4 and 80 characters','translation_key'=>$usernameError],422);
       require_valid_password((string)$d['account_password']);
       if(!in_array($d['account_role'],['Administrator','HR Officer','Department Head','Employee','Management'],true))out(['error'=>'Invalid account role'],422);
     }
     $collegeValue=$d['college_id']??null;
     $departmentValue=$d['department_id']??($d['dept_id']??null);
     $collegeId=$collegeValue===null || $collegeValue===''?null:filter_var($collegeValue,FILTER_VALIDATE_INT);
     $deptId=$departmentValue===null || $departmentValue===''?null:filter_var($departmentValue,FILTER_VALIDATE_INT);
     if($collegeId===false || $deptId===false)out(['error'=>'Select a valid college and department'],422);
     if(($collegeId===null)!==($deptId===null))out(['error'=>'Select both a college and department'],422);
     if($collegeId!==null) {
        $department=db()->prepare('SELECT 1 FROM departments WHERE dept_id=? AND college_id=?');
        $department->execute([$deptId,$collegeId]);
        if(!$department->fetchColumn())out(['error'=>'Department does not belong to the selected college'],422);
     }
     $pdo=db();
     $pdo->beginTransaction();
     try {
       $s=$pdo->prepare('INSERT INTO employees(staff_no,full_name,sex,birth_date,phone,email,position,college_id,dept_id,hire_date,salary,annual_leave_balance) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
       $s->execute([$d['staff_no']??null,trim($d['full_name']),$d['sex']??null,$d['birth_date']??null,$d['phone']??null,$d['email']??null,$d['position']??null,$collegeId,$deptId,$d['hire_date']??null,max(0,(float)($d['salary']??0)),max(0,(float)($d['annual_leave_balance']??20))]);
       $empId=(int)$pdo->lastInsertId();
       if($hasAccount){
         $account=$pdo->prepare("INSERT INTO users(username,password_hash,role,status,emp_id) VALUES(?,?,?,'Active',?)");
         $account->execute([trim((string)$d['account_username']),password_hash((string)$d['account_password'],PASSWORD_DEFAULT),$d['account_role'],$empId]);
       }
       $pdo->commit();
     } catch(Throwable $e) {
       if($pdo->inTransaction())$pdo->rollBack();
       throw $e;
     }
     log_action('CREATE_EMPLOYEE',trim($d['full_name']));
     $employeeDetails=[
       'title'=>'Employee record created',
       'event_type'=>'employee_created',
       'request_type'=>'Employee record',
       'employee_id'=>$empId,
       'employee_name'=>trim($d['full_name']),
       'status'=>'Created',
       'sender'=>$user['full_name'],
       'action'=>'A new employee record was created in the HRMS.',
     ];
     notify_roles(['Administrator','HR Officer'],'A new employee record was created.',$employeeDetails);
     if($hasAccount)notify_employee($empId,'Your MAU HRMS employee account has been created.',[
       'title'=>'Employee account created',
       'event_type'=>'account_created',
       'request_type'=>'Employee account',
       'employee_id'=>$empId,
       'employee_name'=>trim($d['full_name']),
       'role'=>$d['account_role'],
       'status'=>'Active',
       'sender'=>$user['full_name'],
       'action'=>'Your employee record and sign-in account are ready. Contact the administrator for your sign-in credentials.',
     ]);
     out(['emp_id'=>$empId],201);
   }
   if($method==='PUT' && $id){json_input_required($d,['full_name']);$collegeValue=$d['college_id']??null;$departmentValue=$d['department_id']??($d['dept_id']??null);$collegeId=$collegeValue===null||$collegeValue===''?null:filter_var($collegeValue,FILTER_VALIDATE_INT);$deptId=$departmentValue===null||$departmentValue===''?null:filter_var($departmentValue,FILTER_VALIDATE_INT);if($collegeId===false||$deptId===false||(($collegeId===null)!==($deptId===null)))out(['error'=>'Select both a valid college and department'],422);if($collegeId!==null){$s=db()->prepare('SELECT 1 FROM departments WHERE dept_id=? AND college_id=?');$s->execute([$deptId,$collegeId]);if(!$s->fetchColumn())out(['error'=>'Department does not belong to the selected college'],422);}$s=db()->prepare('UPDATE employees SET staff_no=?,full_name=?,sex=?,birth_date=?,phone=?,email=?,position=?,college_id=?,dept_id=?,hire_date=?,salary=?,annual_leave_balance=? WHERE emp_id=?');$s->execute([$d['staff_no']??null,trim($d['full_name']),$d['sex']??null,$d['birth_date']?:null,$d['phone']??null,$d['email']??null,$d['position']??null,$collegeId,$deptId,$d['hire_date']?:null,max(0,(float)($d['salary']??0)),max(0,(float)($d['annual_leave_balance']??20)),$id]);if(!$s->rowCount()){$s=db()->prepare('SELECT 1 FROM employees WHERE emp_id=?');$s->execute([$id]);if(!$s->fetchColumn())out(['error'=>'Employee not found'],404);}log_action('UPDATE_EMPLOYEE',(string)$id);notify_employee($id,'Your employee record was updated.',[
     'title'=>'Employee record updated',
     'event_type'=>'employee_updated',
     'request_type'=>'Employee record',
     'employee_id'=>$id,
     'status'=>'Updated',
     'sender'=>$user['full_name'],
     'action'=>'An administrator or HR officer updated your employee record. Contact HR if any information is incorrect.',
   ]);out(['ok'=>true]);}
   if($method==='DELETE' && $id){require_role(['Administrator']);if((int)($user['emp_id']??0)===$id)out(['error'=>'You cannot delete the employee record linked to your own account'],422);$pdo=db();$pdo->beginTransaction();try{$s=$pdo->prepare('SELECT emp_id FROM employees WHERE emp_id=? FOR UPDATE');$s->execute([$id]);if(!$s->fetchColumn()){$pdo->rollBack();out(['error'=>'Employee not found'],404);}$pdo->prepare("UPDATE users SET status='Inactive',emp_id=NULL WHERE emp_id=?")->execute([$id]);$pdo->prepare('DELETE FROM employees WHERE emp_id=?')->execute([$id]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}log_action('DELETE_EMPLOYEE',(string)$id);out(['ok'=>true]);}
 }
 if($resource==='vacancies') {
   if($method==='GET'){
     $public=($parts[1]??'')==='public';$sql='SELECT v.*,u.username created_by_name FROM vacancies v LEFT JOIN users u ON u.user_id=v.created_by';
     if($public)$sql.=" WHERE v.status='Published' AND v.deadline>=CURDATE()";
     $sql.=' ORDER BY v.created_at DESC';out(db()->query($sql)->fetchAll());
   }
   if($method==='POST'&&($parts[1]??'')==='apply'){
     json_input_required($d,['vacancy_id','applicant_name','email']);
     $vacancyId=(int)$d['vacancy_id'];
     $s=db()->prepare("SELECT vacancy_id,title FROM vacancies WHERE vacancy_id=? AND status='Published' AND deadline>=CURDATE()");
     $s->execute([$vacancyId]);$vacancy=$s->fetch();
     if(!$vacancy)out(['error'=>'Vacancy is not accepting applications'],422);
     $applicantName=trim((string)$d['applicant_name']);$email=trim((string)$d['email']);$phone=trim((string)($d['phone']??''));
     $s=db()->prepare('INSERT INTO applications(vacancy_id,applicant_name,email,phone) VALUES(?,?,?,?)');
     $s->execute([$vacancyId,$applicantName,$email,$phone!==''?$phone:null]);
     $applicationId=(int)db()->lastInsertId();
     notify_roles(['Administrator','HR Officer'],'A new application was submitted for '.$vacancy['title'].'.',[
       'title'=>'New job application',
       'event_type'=>'application_submitted',
       'request_type'=>'Job application',
       'application_id'=>$applicationId,
       'vacancy_id'=>$vacancyId,
       'vacancy_title'=>$vacancy['title'],
       'applicant_name'=>$applicantName,
       'applicant_email'=>$email,
       'applicant_phone'=>$phone,
       'status'=>'Submitted',
       'sender'=>$applicantName,
       'action'=>'Review the application in the Applications section.',
     ]);
     out(['app_id'=>$applicationId],201);
   }
   require_role(['Administrator','HR Officer']);
   if($method==='POST'){
     json_input_required($d,['title','requirements','deadline']);
     $title=trim((string)$d['title']);$status=$d['status']??'Draft';
     $s=db()->prepare('INSERT INTO vacancies(title,requirements,deadline,status,created_by) VALUES(?,?,?,?,?)');
     $s->execute([$title,$d['requirements'],$d['deadline'],$status,$user['user_id']]);
     $vacancyId=(int)db()->lastInsertId();log_action('CREATE_VACANCY',$title);
     notify_roles(['Administrator','HR Officer'],'A vacancy was added to the HRMS.',[
       'title'=>'Vacancy created',
       'event_type'=>'vacancy_created',
       'request_type'=>'Vacancy',
       'vacancy_id'=>$vacancyId,
       'vacancy_title'=>$title,
       'deadline'=>$d['deadline'],
       'status'=>$status,
       'sender'=>$user['full_name'],
       'action'=>'The vacancy is now available in the Vacancies section.',
     ]);
     out(['vacancy_id'=>$vacancyId],201);
   }
   if($method==='PATCH'&&$id){
     $status=$d['status']??'Closed';
     $s=db()->prepare('SELECT title FROM vacancies WHERE vacancy_id=?');$s->execute([$id]);$title=$s->fetchColumn();
     if($title===false)out(['error'=>'Vacancy not found'],404);
     db()->prepare('UPDATE vacancies SET status=? WHERE vacancy_id=?')->execute([$status,$id]);
     notify_roles(['Administrator','HR Officer'],'Vacancy status changed to '.$status.'.',[
       'title'=>'Vacancy status updated',
       'event_type'=>'vacancy_updated',
       'request_type'=>'Vacancy',
       'vacancy_id'=>$id,
       'vacancy_title'=>$title,
       'status'=>$status,
       'sender'=>$user['full_name'],
       'action'=>'The vacancy status was updated.',
     ]);
     out(['ok'=>true]);
   }
 }
 if($resource==='applications') {
   require_role(['Administrator','HR Officer']);
   if($method==='GET')out(db()->query('SELECT a.*,v.title vacancy_title FROM applications a JOIN vacancies v ON v.vacancy_id=a.vacancy_id ORDER BY a.created_at DESC')->fetchAll());
   if($method==='PATCH'&&$id){
     $status=$d['status']??'';
     if(!in_array($status,['Shortlisted','Rejected','Hired'],true))out(['error'=>'Invalid application status'],422);
     $s=db()->prepare('SELECT a.applicant_name,a.email,v.title FROM applications a JOIN vacancies v ON v.vacancy_id=a.vacancy_id WHERE a.app_id=?');
     $s->execute([$id]);$application=$s->fetch();
     if(!$application)out(['error'=>'Application not found'],404);
     db()->prepare('UPDATE applications SET status=? WHERE app_id=?')->execute([$status,$id]);
     notify_roles(['Administrator','HR Officer'],'An application status was updated.',[
       'title'=>'Application status updated',
       'event_type'=>'application_updated',
       'request_type'=>'Job application',
       'application_id'=>$id,
       'vacancy_title'=>$application['title'],
       'applicant_name'=>$application['applicant_name'],
       'applicant_email'=>$application['email'],
       'status'=>$status,
       'sender'=>$user['full_name'],
       'action'=>'An application was marked '.$status.'.',
     ]);
     out(['ok'=>true]);
   }
 }
 if($resource==='leave') {
   if($method==='GET') { $where='';$args=[];if($user['role']==='Employee'){$where=' WHERE l.emp_id=?';$args[]=$user['emp_id'];}elseif($user['role']==='Department Head'){$where=' WHERE l.status="Pending Head" OR l.emp_id IN (SELECT emp_id FROM employees WHERE dept_id=(SELECT dept_id FROM employees WHERE emp_id=?))';$args[]=$user['emp_id'];} $s=db()->prepare('SELECT l.*,e.full_name,e.staff_no FROM leave_requests l JOIN employees e ON e.emp_id=l.emp_id'.$where.' ORDER BY l.created_at DESC');$s->execute($args);out($s->fetchAll()); }
   if($method==='POST'){
     require_role(['Employee']);
     json_input_required($d,['type','start_date','end_date','reason']);
     if(!$user['emp_id'])out(['error'=>'This account is not linked to an employee'],422);
     $start=new DateTime($d['start_date']);$end=new DateTime($d['end_date']);
     if($end<$start)out(['error'=>'End date must not be before start date'],422);
     $days=(int)$start->diff($end)->days+1;
     if($d['type']==='Annual'){$s=db()->prepare('SELECT annual_leave_balance FROM employees WHERE emp_id=?');$s->execute([$user['emp_id']]);if((float)$s->fetchColumn()<$days)out(['error'=>'Insufficient annual leave balance'],422);}
     $s=db()->prepare('INSERT INTO leave_requests(emp_id,type,start_date,end_date,days,reason) VALUES(?,?,?,?,?,?)');
     $s->execute([$user['emp_id'],$d['type'],$d['start_date'],$d['end_date'],$days,trim($d['reason'])]);
     $leaveId=(int)db()->lastInsertId();
     $heads=db()->prepare("SELECT u.user_id FROM users u JOIN employees e ON e.emp_id=u.emp_id JOIN employees applicant ON applicant.dept_id=e.dept_id WHERE u.role='Department Head' AND applicant.emp_id=? AND u.status='Active'");
     $heads->execute([$user['emp_id']]);$headUserIds=array_map(static fn($head)=>(int)$head['user_id'],$heads->fetchAll());
     $details=[
       'title'=>'Leave request submitted',
       'event_type'=>'leave_request',
       'request_type'=>'Leave request',
       'request_id'=>$leaveId,
       'leave_type'=>$d['type'],
       'status'=>'Pending Head',
       'start_date'=>$d['start_date'],
       'end_date'=>$d['end_date'],
       'days'=>$days,
       'reason'=>trim($d['reason']),
       'sender'=>$user['full_name'],
       'action'=>'Review the employee leave request.',
     ];
     $delivery=notification_delivery_defaults();
     foreach($headUserIds as $recipientId)$delivery=merge_notification_delivery($delivery,notify_user($recipientId,'A leave request needs your review.',$details));
     $delivery=merge_notification_delivery($delivery,notify_roles(['Administrator','HR Officer'],'An employee leave request is awaiting review.',$details));
     log_action('APPLY_LEAVE',(string)$leaveId);
     out(['ok'=>true,'leave_id'=>$leaveId,'email_notification_sent'=>$delivery['email_sent'],'sms_notification_sent'=>$delivery['sms_sent']],201);
   }
   if($method==='POST' && ($parts[1]??'')==='decision') { /* use /leave/decision/{id} is not used; see PATCH below */ }
   if($method==='PATCH'&&$id){
     $s=db()->prepare('SELECT l.*,e.full_name FROM leave_requests l JOIN employees e ON e.emp_id=l.emp_id WHERE l.leave_id=?');
     $s->execute([$id]);$leave=$s->fetch();
     if(!$leave)out(['error'=>'Leave request not found'],404);
     $decision=$d['decision']??'';
     if(!in_array($decision,['approve','reject'],true))out(['error'=>'Decision must be approve or reject'],422);
     $comment=trim((string)($d['comment']??''));
     $status='';
     if($user['role']==='Department Head'&&$leave['status']==='Pending Head'){
       $status=$decision==='approve'?'Pending HR':'Rejected';
       db()->prepare('UPDATE leave_requests SET status=?,head_comment=? WHERE leave_id=?')->execute([$status,$comment,$id]);
       $details=['title'=>'Leave request '.$status,'event_type'=>'leave_request','request_type'=>'Leave request','request_id'=>$id,'leave_type'=>$leave['type'],'status'=>$status,'start_date'=>$leave['start_date'],'end_date'=>$leave['end_date'],'days'=>$leave['days'],'reason'=>$leave['reason'],'sender'=>$leave['full_name'],'reviewed_by'=>$user['full_name'],'comment'=>$comment,'action'=>'The department head '.$decision.'d the leave request.'];
       if($decision==='approve')notify_roles(['HR Officer'],'A leave request is waiting for HR review.',$details);
       $employeeQuery=db()->prepare('SELECT user_id FROM users WHERE emp_id=? AND status=\'Active\'');$employeeQuery->execute([$leave['emp_id']]);$employeeUser=$employeeQuery->fetchColumn();
       if($employeeUser)notify_user((int)$employeeUser,'Your leave request is now '.$status.'.',$details);
     } elseif($user['role']==='HR Officer'&&$leave['status']==='Pending HR'){
       $status=$decision==='approve'?'Approved':'Rejected';
       $pdo=db();$pdo->beginTransaction();
       try{
         if($decision==='approve'&&$leave['type']==='Annual'){
           $balance=$pdo->prepare('UPDATE employees SET annual_leave_balance=annual_leave_balance-? WHERE emp_id=? AND annual_leave_balance>=?');
           $balance->execute([$leave['days'],$leave['emp_id'],$leave['days']]);
           if(!$balance->rowCount())throw new RuntimeException('Insufficient leave balance');
         }
         $pdo->prepare('UPDATE leave_requests SET status=?,hr_comment=? WHERE leave_id=?')->execute([$status,$comment,$id]);
         $pdo->commit();
       }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();out(['error'=>$error->getMessage()],422);}
       $details=['title'=>'Leave request '.$status,'event_type'=>'leave_request','request_type'=>'Leave request','request_id'=>$id,'leave_type'=>$leave['type'],'status'=>$status,'start_date'=>$leave['start_date'],'end_date'=>$leave['end_date'],'days'=>$leave['days'],'reason'=>$leave['reason'],'sender'=>$leave['full_name'],'reviewed_by'=>$user['full_name'],'comment'=>$comment,'action'=>'HR '.strtolower($status).' the leave request.'];
       $employeeQuery=db()->prepare('SELECT user_id FROM users WHERE emp_id=? AND status=\'Active\'');$employeeQuery->execute([$leave['emp_id']]);$employeeUser=$employeeQuery->fetchColumn();
       if($employeeUser)notify_user((int)$employeeUser,'Your leave request was '.strtolower($status).' by HR.',$details);
     } elseif($user['role']==='Administrator'&&in_array($leave['status'],['Pending Head','Pending HR'],true)){
       $status=$decision==='approve'?'Approved':'Rejected';
       $pdo=db();$pdo->beginTransaction();
       try{
         if($decision==='approve'&&$leave['type']==='Annual'){
           $balance=$pdo->prepare('UPDATE employees SET annual_leave_balance=annual_leave_balance-? WHERE emp_id=? AND annual_leave_balance>=?');
           $balance->execute([$leave['days'],$leave['emp_id'],$leave['days']]);
           if(!$balance->rowCount())throw new RuntimeException('Insufficient leave balance');
         }
         $pdo->prepare('UPDATE leave_requests SET status=?,hr_comment=? WHERE leave_id=?')->execute([$status,$comment,$id]);
         $pdo->commit();
       }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();out(['error'=>$error->getMessage()],422);}
       $details=['title'=>'Leave request '.$status,'event_type'=>'leave_request','request_type'=>'Leave request','request_id'=>$id,'leave_type'=>$leave['type'],'status'=>$status,'start_date'=>$leave['start_date'],'end_date'=>$leave['end_date'],'days'=>$leave['days'],'reason'=>$leave['reason'],'sender'=>$leave['full_name'],'reviewed_by'=>$user['full_name'],'comment'=>$comment,'action'=>'An administrator '.strtolower($status).' the leave request.'];
       $employeeQuery=db()->prepare('SELECT user_id FROM users WHERE emp_id=? AND status=\'Active\'');$employeeQuery->execute([$leave['emp_id']]);$employeeUser=$employeeQuery->fetchColumn();
       if($employeeUser)notify_user((int)$employeeUser,'Your leave request was '.strtolower($status).' by an Administrator.',$details);
     } else out(['error'=>'You cannot decide this request at its current stage'],403);
     log_action('LEAVE_DECISION',$id.':'.$decision);
     out(['ok'=>true,'status'=>$status]);
   }
 }
 if($resource==='attendance') {
   require_role(['Administrator','HR Officer','Department Head','Employee','Management']);
   if($method==='GET'){
     $sql='SELECT a.*,e.full_name,e.staff_no FROM attendance a JOIN employees e ON e.emp_id=a.emp_id';$args=[];
     if($user['role']==='Employee'){$sql.=' WHERE a.emp_id=?';$args[]=$user['emp_id'];}
     $sql.=' ORDER BY attendance_date DESC LIMIT 500';
     $s=db()->prepare($sql);$s->execute($args);out($s->fetchAll());
   }
   if($method==='POST'){
     require_role(['Administrator','HR Officer']);
     json_input_required($d,['emp_id','attendance_date','status']);
     $employeeId=(int)$d['emp_id'];
     $s=db()->prepare('INSERT INTO attendance(emp_id,attendance_date,status,notes) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),notes=VALUES(notes)');
     $s->execute([$employeeId,$d['attendance_date'],$d['status'],$d['notes']??null]);
     notify_employee($employeeId,'Your attendance record was updated.',[
       'title'=>'Attendance updated',
       'event_type'=>'attendance_updated',
       'request_type'=>'Attendance record',
       'attendance_date'=>$d['attendance_date'],
       'status'=>$d['status'],
       'notes'=>$d['notes']??'',
       'sender'=>$user['full_name'],
       'action'=>'Your attendance record was updated by HR.',
     ]);
     out(['ok'=>true],201);
   }
 }
 if($resource==='payroll') {
   require_role(['Administrator','HR Officer','Employee','Management']);
   if($method==='GET'){
     $sql='SELECT p.*,e.full_name,e.staff_no FROM payroll p JOIN employees e ON e.emp_id=p.emp_id';$args=[];
     if($user['role']==='Employee'){$sql.=' WHERE p.emp_id=?';$args[]=$user['emp_id'];}
     $sql.=' ORDER BY pay_month DESC';
     $s=db()->prepare($sql);$s->execute($args);out($s->fetchAll());
   }
   if($method==='POST'){
     require_role(['Administrator','HR Officer']);
     json_input_required($d,['emp_id','pay_month']);
     $employeeId=(int)$d['emp_id'];
     $s=db()->prepare('SELECT salary FROM employees WHERE emp_id=?');$s->execute([$employeeId]);$basic=$s->fetchColumn();
     if($basic===false)out(['error'=>'Employee not found'],404);
     $allow=(float)($d['allowance']??0);$tax=(float)($d['tax']??0);$pension=(float)($d['pension']??0);$other=(float)($d['other_deductions']??0);
     $net=(float)$basic+$allow-$tax-$pension-$other;
     if($net<0)out(['error'=>'Deductions cannot exceed gross salary'],422);
     $s=db()->prepare('INSERT INTO payroll(emp_id,pay_month,basic_salary,allowance,tax,pension,other_deductions,net_salary) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE basic_salary=VALUES(basic_salary),allowance=VALUES(allowance),tax=VALUES(tax),pension=VALUES(pension),other_deductions=VALUES(other_deductions),net_salary=VALUES(net_salary)');
     $s->execute([$employeeId,$d['pay_month'],$basic,$allow,$tax,$pension,$other,$net]);
     notify_employee($employeeId,'Your payroll record for '.$d['pay_month'].' is available.',[
       'title'=>'Payroll processed',
       'event_type'=>'payroll_processed',
       'request_type'=>'Payroll',
       'pay_month'=>$d['pay_month'],
       'status'=>'Processed',
       'sender'=>$user['full_name'],
       'action'=>'Your payroll record is available in the Payroll section. Sign in to view the details.',
     ]);
     out(['net_salary'=>$net],201);
   }
 }
 if($resource==='evaluations') {
   if($method==='GET'&&($parts[1]??'')==='employees'){
     require_role(['Administrator','HR Officer','Department Head','Employee']);
     if(in_array($user['role'],['Administrator','HR Officer'],true))out(db()->query('SELECT emp_id,staff_no,full_name FROM employees ORDER BY emp_id DESC')->fetchAll());
     if($user['role']==='Employee'){
       $s=db()->prepare('SELECT e.emp_id,e.staff_no,e.full_name FROM users u JOIN employees e ON e.emp_id=u.emp_id WHERE u.user_id=? AND u.status=\'Active\'');
       $s->execute([$user['user_id']]);out($s->fetchAll());
     }
     $s=db()->prepare('SELECT e.emp_id,e.staff_no,e.full_name FROM employees e JOIN employees head ON head.dept_id=e.dept_id WHERE head.emp_id=? ORDER BY e.emp_id DESC');
     $s->execute([$user['emp_id']]);out($s->fetchAll());
   }
   if($method==='GET'){
     require_role(['Administrator','HR Officer','Department Head','Employee','Management']);
     $sql='SELECT v.*,e.full_name,e.staff_no FROM evaluations v JOIN employees e ON e.emp_id=v.emp_id';$args=[];
     if($user['role']==='Employee'){$sql.=' WHERE v.emp_id=?';$args[]=$user['emp_id'];}
     $sql.=' ORDER BY v.created_at DESC';$s=db()->prepare($sql);$s->execute($args);out($s->fetchAll());
   }
   if($method==='POST'){
     require_role(['Administrator','Department Head']);
     json_input_required($d,['emp_id','period','score']);
     $employeeId=(int)$d['emp_id'];$score=(float)$d['score'];
     if($score<0||$score>100)out(['error'=>'Score must be between 0 and 100'],422);
     $employeeQuery=$user['role']==='Department Head'
       ? db()->prepare('SELECT 1 FROM employees target JOIN employees head ON head.dept_id=target.dept_id WHERE target.emp_id=? AND head.emp_id=?')
       : db()->prepare('SELECT 1 FROM employees WHERE emp_id=?');
     $employeeQuery->execute($user['role']==='Department Head'?[$employeeId,$user['emp_id']]:[$employeeId]);
     if(!$employeeQuery->fetchColumn())out(['error'=>'Employee not found'],404);
     $period=trim((string)$d['period']);$comment=trim((string)($d['comment']??''));
     $s=db()->prepare('INSERT INTO evaluations(emp_id,evaluator_id,period,score,comment) VALUES(?,?,?,?,?)');
     $s->execute([$employeeId,$user['user_id'],$period,$score,$comment!==''?$comment:null]);
     $evaluationId=(int)db()->lastInsertId();
     notify_employee($employeeId,'A performance evaluation for '.$period.' is available.',[
       'title'=>'Evaluation available',
       'event_type'=>'evaluation_created',
       'request_type'=>'Performance evaluation',
       'evaluation_id'=>$evaluationId,
       'period'=>$period,
       'score'=>$score,
       'comment'=>$comment,
       'status'=>'Available',
       'sender'=>$user['full_name'],
       'action'=>'Your performance evaluation is available in the Evaluations section.',
     ]);
     out(['eval_id'=>$evaluationId],201);
   }
 }
 if($resource==='notifications') {
   $user=require_login();
   $notificationSchema=ensure_notifications_schema();
   $notificationIdColumn=$notificationSchema['id'];
   $notificationTypeColumn=$notificationSchema['type'];
   if($method==='GET'&&($parts[1]??'')==='unread-count') {
     $s=db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
     $s->execute([$user['user_id']]);
     out(['count'=>(int)$s->fetchColumn()]);
   }
  if($method==='POST') {
    require_role(['Administrator','HR Officer']);
    json_input_required($d,['user_id','message']);
    $targetUserId=filter_var($d['user_id'],FILTER_VALIDATE_INT);
    if($targetUserId===false || $targetUserId<1) out(['error'=>'Select a valid user'],422);
    $target=db()->prepare('SELECT user_id FROM users WHERE user_id=?');
    $target->execute([$targetUserId]);
    if(!$target->fetchColumn()) out(['error'=>'User not found'],404);
    $message=trim((string)$d['message']);
    if($message==='') out(['error'=>'Notification message is required'],422);
    $title=trim((string)($d['title']??'MAU HRMS notification'));
    $type=trim((string)($d['type']??'system'));
    $details=is_array($d['details'] ?? null)?$d['details']:[];
    if(!is_array($details)) $details=[];
    try {
      $s=db()->prepare("INSERT INTO notifications(user_id,title,`$notificationTypeColumn`,message,details_json) VALUES(?,?,?,?,?)");
      $s->execute([$targetUserId,$title!==''?$title:'MAU HRMS notification',$type!==''?$type:'system',$message,json_encode($details,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    } catch(Throwable $error) {
      error_log('HRMS could not create notification: '.$error->getMessage());
      out(['error'=>'Unable to create this notification'],422);
    }
    $notificationId=(int)db()->lastInsertId();
    out(['notification_id'=>$notificationId,'ok'=>true],201);
  }
  if($method==='PUT' && $id) {
    require_role(['Administrator','HR Officer']);
    $fields=[];$values=[];
    if(array_key_exists('user_id',$d)) {
      $targetUserId=filter_var($d['user_id'],FILTER_VALIDATE_INT);
      if($targetUserId===false || $targetUserId<1) out(['error'=>'Select a valid user'],422);
      $target=db()->prepare('SELECT user_id FROM users WHERE user_id=?');
      $target->execute([$targetUserId]);
      if(!$target->fetchColumn()) out(['error'=>'User not found'],404);
      $fields[]='user_id=?'; $values[]=$targetUserId;
    }
    $title=array_key_exists('title',$d)?trim((string)$d['title']):null;
    if($title!==null) {$fields[]='title=?'; $values[]=$title!==''?$title:'MAU HRMS notification';}
    $message=array_key_exists('message',$d)?trim((string)$d['message']):null;
    if($message!==null) { if($message==='') out(['error'=>'Notification message is required'],422); $fields[]='message=?'; $values[]=$message; }
    $type=array_key_exists('type',$d)?trim((string)$d['type']):null;
    if($type!==null) {$fields[]='`'.$notificationTypeColumn.'`=?'; $values[]=$type!==''?$type:'system';}
    $details=array_key_exists('details',$d)?$d['details']:null;
    if($details!==null) { if(!is_array($details)) $details=[]; $fields[]='details_json=?'; $values[] = json_encode($details, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); }
    if(!$fields) out(['error'=>'Provide at least one field to update'],422);
    $ownerCheck='SELECT user_id FROM notifications WHERE `'.$notificationIdColumn.'`=?';
    $existing=db()->prepare($ownerCheck);
    $existing->execute([$id]);
    $existingRecord=$existing->fetch();
    if(!$existingRecord) out(['error'=>'Notification not found'],404);
    $values[]=$id;
    $sql='UPDATE notifications SET '.implode(', ',$fields).' WHERE `'.$notificationIdColumn.'`=?';
    $s=db()->prepare($sql);
    $s->execute($values);
    if(!$s->rowCount()) out(['error'=>'Notification not found'],404);
    out(['ok'=>true]);
  }
  if($method==='DELETE' && $id) {
    $canManageAll=in_array($user['role'],['Administrator','HR Officer'],true);
    $sql='DELETE FROM notifications WHERE `'.$notificationIdColumn.'`=?';
    $values=[$id];
    if(!$canManageAll) {
      $sql.=' AND user_id=?';
      $values[]=(int)$user['user_id'];
    }
    $s=db()->prepare($sql);
    $s->execute($values);
    if(!$s->rowCount()) out(['error'=>'Notification not found'],404);
    out(['ok'=>true]);
  }
   if($method==='GET'){
    $canViewAll=in_array($user['role'],['Administrator','HR Officer'],true) && strtolower((string)($_GET['scope']??''))==='all';
    if($canViewAll){
      $s=db()->prepare("SELECT n.`$notificationIdColumn` AS not_id,n.user_id,u.username,COALESCE(e.full_name,u.username) AS recipient_name,n.title,n.`$notificationTypeColumn` AS event_type,n.message,n.details_json,n.is_read,n.created_at FROM notifications n LEFT JOIN users u ON u.user_id=n.user_id LEFT JOIN employees e ON e.emp_id=u.emp_id ORDER BY n.created_at DESC LIMIT 200");
      $s->execute();
    } else {
      $s=db()->prepare("SELECT `$notificationIdColumn` AS not_id,user_id,title,`$notificationTypeColumn` AS event_type,message,details_json,is_read,created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100");
      $s->execute([$user['user_id']]);
    }
    $items=$s->fetchAll();
     foreach($items as &$item){
       $details=$item['details_json']!==null?json_decode($item['details_json'],true):[];
       $item['details']=is_array($details)?$details:[];
       unset($item['details_json']);
     }
     unset($item);
     out($items);
   }
   if($method==='PATCH'&&($parts[1]??'')==='read-all') {
     $s=db()->prepare('UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0');
     $s->execute([$user['user_id']]);
     out(['ok'=>true]);
   }
   if($method==='PATCH'&&$id){$s=db()->prepare("UPDATE notifications SET is_read=1 WHERE `$notificationIdColumn`=? AND user_id=?");$s->execute([$id,$user['user_id']]);out(['ok'=>true]);}
 }
 if($resource==='reports'&&$method==='GET'){require_role(['Administrator','HR Officer','Management']);$pdo=db();out(['employees_by_department'=>$pdo->query('SELECT COALESCE(d.name,"Unassigned") department,COUNT(*) total FROM employees e LEFT JOIN departments d ON d.dept_id=e.dept_id GROUP BY d.name ORDER BY total DESC')->fetchAll(),'leave_by_status'=>$pdo->query('SELECT status,COUNT(*) total FROM leave_requests GROUP BY status')->fetchAll(),'attendance_by_status'=>$pdo->query('SELECT status,COUNT(*) total FROM attendance GROUP BY status')->fetchAll(),'payroll_monthly'=>$pdo->query('SELECT pay_month,COUNT(*) employees,SUM(net_salary) total_net FROM payroll GROUP BY pay_month ORDER BY pay_month DESC LIMIT 12')->fetchAll()]);}
 out(['error'=>'Endpoint not found'],404);
} catch(PDOException $e) {
  error_log(sprintf('HRMS API database error [%s %s]: %s', $method, $path, $e->getMessage()));
  if(str_contains($e->getMessage(),'Duplicate entry')) out(['error'=>'A record with this unique value already exists'],409);
  $mysqlCode=(int)($e->errorInfo[1]??0);
  if($resource==='users'&&$mysqlCode===1146) {
    out(['error'=>'The users table is missing. Import the users table schema before using account management.','code'=>'users_table_missing'],503);
  }
  if($resource==='users'&&$mysqlCode===1054) {
    out(['error'=>'The users table is outdated and is missing a column required by this application. Compare it with the current schema and apply the needed migration.','code'=>'users_schema_outdated'],503);
  }
  if(in_array($mysqlCode,[1049,2002,2003,2005],true)||str_starts_with((string)$e->getCode(),'08')) {
    out(['error'=>'The database is unavailable. Check the MySQL service and backend database configuration.','code'=>'database_unavailable'],503);
  }
  if($resource==='users') {
    out(['error'=>'User account database operation failed. Check the server log for the SQL error and verify the database schema and permissions.','code'=>'users_query_failed'],500);
  }
  out(['error'=>'Database operation failed'],500);
} catch(Throwable $e) {
  error_log(sprintf('HRMS API error [%s %s]: %s', $method, $path, $e->getMessage()));
  out(['error'=>'Server error'],500);
}
