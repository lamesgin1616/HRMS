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

function out($data, int $status=200): never { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function body(): array { $x=json_decode(file_get_contents('php://input'), true); return is_array($x)?$x:$_POST; }
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
function notify_user(int $id,string $message): void { $s=db()->prepare('INSERT INTO notifications(user_id,message) VALUES(?,?)'); $s->execute([$id,$message]); }
function notify_roles(array $roles,string $message): void { $placeholders=implode(',',array_fill(0,count($roles),'?'));$s=db()->prepare("SELECT user_id FROM users WHERE status='Active' AND role IN ($placeholders)");$s->execute($roles);foreach($s->fetchAll() as $recipient)notify_user((int)$recipient['user_id'],$message); }
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
  if($username==='') out(['error'=>'Username is required'],422);
  if(strlen($password)<8) out(['error'=>'Password must be at least 8 characters long'],422);
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
      notify_roles(['Administrator'],'A role request is awaiting review.');
    }
    $pdo->commit();
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
  if($loginUsername==='' || $loginPassword==='')out(['error'=>'Invalid credentials or account temporarily locked'],401);
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
   if($username!==null && strlen($username)>80)out(['error'=>'Username must be 80 characters or fewer','translation_key'=>'usernameTooLong'],422);
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
   $s=$pdo->prepare('SELECT u.user_id,u.username,u.emp_id,COALESCE(e.full_name,u.username) AS full_name,e.phone,e.email,e.sex,e.birth_date,e.position FROM users u LEFT JOIN employees e ON e.emp_id=u.emp_id WHERE u.user_id=?');
   $s->execute([(int)$user['user_id']]);
   out($s->fetch());
 }
 if($resource==='change-password' && $method==='POST') {
   json_input_required($d,['current_password','new_password','confirm_password']);
   $currentPassword=is_string($d['current_password'])?$d['current_password']:'';
   $newPassword=is_string($d['new_password'])?$d['new_password']:'';
   $confirmPassword=is_string($d['confirm_password'])?$d['confirm_password']:'';
   if(strlen($newPassword)<8)out(['error'=>'New password must be at least 8 characters long','translation_key'=>'passwordTooShort'],422);
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
   if($method==='GET')out(db()->query('SELECT u.user_id,u.username,u.role,u.status,u.emp_id,e.full_name,e.staff_no FROM users u LEFT JOIN employees e ON e.emp_id=u.emp_id ORDER BY u.user_id DESC')->fetchAll());
   if($method==='POST') {
     json_input_required($d,['username','password','role']);
     $username=trim((string)$d['username']);
     $password=(string)$d['password'];
     if($username==='')out(['error'=>'Username is required'],422);
     if(strlen($password)<8)out(['error'=>'Password must be at least 8 characters long'],422);
     if(!in_array($d['role'],$roles,true))out(['error'=>'Invalid account role'],422);
     $status=$d['status']??'Active';
     if(!in_array($status,['Active','Inactive'],true))out(['error'=>'Invalid account status'],422);
     $empId=isset($d['emp_id'])&&$d['emp_id']!==''?filter_var($d['emp_id'],FILTER_VALIDATE_INT):null;
     if($empId===false || ($empId!==null && $empId<1))out(['error'=>'Select a valid employee'],422);
     if($empId!==null){$s=db()->prepare('SELECT 1 FROM employees WHERE emp_id=?');$s->execute([$empId]);if(!$s->fetchColumn())out(['error'=>'Employee not found'],404);}
     try{$s=db()->prepare('INSERT INTO users(username,password_hash,role,status,emp_id) VALUES(?,?,?,?,?)');$s->execute([$username,password_hash($password,PASSWORD_DEFAULT),$d['role'],$status,$empId]);}
     catch(PDOException $e){if((string)$e->getCode()==='23000')out(['error'=>'Username or employee already has an account'],409);throw $e;}
     log_action('CREATE_USER',$username);
     out(['user_id'=>(int)db()->lastInsertId()],201);
   }
   if($method==='PATCH' && $id) {
     $s=db()->prepare('SELECT user_id,username,role,status,emp_id FROM users WHERE user_id=?');
     $s->execute([$id]);$account=$s->fetch();
     if(!$account)out(['error'=>'User account not found'],404);
     $username=trim((string)($d['username']??$account['username']));
     $role=$d['role']??$account['role'];
     $status=$d['status']??$account['status'];
     if($username==='')out(['error'=>'Username is required'],422);
     if(!in_array($role,$roles,true))out(['error'=>'Invalid account role'],422);
     if(!in_array($status,['Active','Inactive'],true))out(['error'=>'Invalid account status'],422);
     if((int)$account['user_id']===(int)$user['user_id'] && ($status!=='Active'||$role!=='Administrator'))out(['error'=>'You cannot deactivate or demote your own administrator account'],422);
     $empId=array_key_exists('emp_id',$d)?($d['emp_id']===''||$d['emp_id']===null?null:filter_var($d['emp_id'],FILTER_VALIDATE_INT)):$account['emp_id'];
     if($empId===false || ($empId!==null && (int)$empId<1))out(['error'=>'Select a valid employee'],422);
     if($empId!==null){$s=db()->prepare('SELECT 1 FROM employees WHERE emp_id=?');$s->execute([$empId]);if(!$s->fetchColumn())out(['error'=>'Employee not found'],404);}
     $password=array_key_exists('password',$d)?(string)$d['password']:'';
     if($password!=='' && strlen($password)<8)out(['error'=>'Password must be at least 8 characters long'],422);
     try {
       if($password!=='')$s=db()->prepare('UPDATE users SET username=?,role=?,status=?,emp_id=?,password_hash=? WHERE user_id=?')->execute([$username,$role,$status,$empId,password_hash($password,PASSWORD_DEFAULT),$id]);
       else $s=db()->prepare('UPDATE users SET username=?,role=?,status=?,emp_id=? WHERE user_id=?')->execute([$username,$role,$status,$empId,$id]);
     } catch(PDOException $e){if((string)$e->getCode()==='23000')out(['error'=>'Username or employee already has an account'],409);throw $e;}
     log_action('UPDATE_USER',(string)$id);
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
       notify_roles(['Administrator','HR Officer'],'An employee profile update request is awaiting review.');
       $requestId=(int)$pdo->lastInsertId();$pdo->commit();
     } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
     log_action('REQUEST_PROFILE_UPDATE',(string)$requestId);
     out(['request_id'=>$requestId],201);
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
       notify_user((int)$request['user_id'],'Your profile update request was '.strtolower($status).'.');
       $pdo->commit();
     } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
     log_action('REVIEW_PROFILE_REQUEST',$status.' request '.$id);
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
   if($username==='')out(['error'=>'Username is required'],422);
   if(strlen((string)$d['password'])<8)out(['error'=>'Password must be at least 8 characters long'],422);
   if(!in_array($d['role'],$roles,true))out(['error'=>'Invalid account role'],422);
   $employee=db()->prepare('SELECT emp_id FROM employees WHERE emp_id=?');
   $employee->execute([$empId]);
   if(!$employee->fetchColumn())out(['error'=>'Employee not found'],404);
   $existing=db()->prepare('SELECT user_id FROM users WHERE emp_id=?');
   $existing->execute([$empId]);
   if($existing->fetchColumn())out(['error'=>'This employee already has a login account'],409);
   $account=db()->prepare("INSERT INTO users(username,password_hash,role,status,emp_id) VALUES(?,?,?,'Active',?)");
   $account->execute([$username,password_hash((string)$d['password'],PASSWORD_DEFAULT),$d['role'],$empId]);
   log_action('CREATE_EMPLOYEE_ACCOUNT',(string)$empId);
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
       $s=$pdo->prepare('SELECT user_id,requested_role,status FROM role_requests WHERE request_id=? FOR UPDATE');
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
     notify_user((int)$request['user_id'],'Your role request was '.strtolower($status).'.');
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
   if($method==='GET') { require_role(['Administrator','HR Officer','Department Head','Employee','Management']); if($user['role']==='Employee'){ $s=db()->prepare('SELECT e.*,d.name department FROM employees e LEFT JOIN departments d ON d.dept_id=e.dept_id WHERE e.emp_id=?');$s->execute([$user['emp_id']]);out($s->fetch()?:[]); } $q=trim($_GET['q']??'');$sql='SELECT e.*,d.name department FROM employees e LEFT JOIN departments d ON d.dept_id=e.dept_id';$args=[];if($q!==''){$sql.=' WHERE e.full_name LIKE ? OR e.staff_no LIKE ? OR e.email LIKE ?';$args=["%$q%","%$q%","%$q%"];}$sql.=' ORDER BY e.emp_id DESC';$s=db()->prepare($sql);$s->execute($args);out($s->fetchAll()); }
   require_role(['Administrator','HR Officer']);
   if($method==='POST'){
     json_input_required($d,['full_name']);
     $hasAccount=trim((string)($d['account_username']??''))!=='' || trim((string)($d['account_password']??''))!=='';
     if($hasAccount){
       require_role(['Administrator']);
       json_input_required($d,['account_username','account_password','account_role']);
       $d['account_username']=trim((string)$d['account_username']);
       if($d['account_username']==='')out(['error'=>'Login username is required'],422);
       if(strlen((string)$d['account_password'])<8)out(['error'=>'Account password must be at least 8 characters long'],422);
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
     out(['emp_id'=>$empId],201);
   }
   if($method==='PUT' && $id){json_input_required($d,['full_name']);$collegeValue=$d['college_id']??null;$departmentValue=$d['department_id']??($d['dept_id']??null);$collegeId=$collegeValue===null||$collegeValue===''?null:filter_var($collegeValue,FILTER_VALIDATE_INT);$deptId=$departmentValue===null||$departmentValue===''?null:filter_var($departmentValue,FILTER_VALIDATE_INT);if($collegeId===false||$deptId===false||(($collegeId===null)!==($deptId===null)))out(['error'=>'Select both a valid college and department'],422);if($collegeId!==null){$s=db()->prepare('SELECT 1 FROM departments WHERE dept_id=? AND college_id=?');$s->execute([$deptId,$collegeId]);if(!$s->fetchColumn())out(['error'=>'Department does not belong to the selected college'],422);}$s=db()->prepare('UPDATE employees SET staff_no=?,full_name=?,sex=?,birth_date=?,phone=?,email=?,position=?,college_id=?,dept_id=?,hire_date=?,salary=?,annual_leave_balance=? WHERE emp_id=?');$s->execute([$d['staff_no']??null,trim($d['full_name']),$d['sex']??null,$d['birth_date']?:null,$d['phone']??null,$d['email']??null,$d['position']??null,$collegeId,$deptId,$d['hire_date']?:null,max(0,(float)($d['salary']??0)),max(0,(float)($d['annual_leave_balance']??20)),$id]);if(!$s->rowCount()){$s=db()->prepare('SELECT 1 FROM employees WHERE emp_id=?');$s->execute([$id]);if(!$s->fetchColumn())out(['error'=>'Employee not found'],404);}log_action('UPDATE_EMPLOYEE',(string)$id);out(['ok'=>true]);}
   if($method==='DELETE' && $id){require_role(['Administrator']);if((int)($user['emp_id']??0)===$id)out(['error'=>'You cannot delete the employee record linked to your own account'],422);$pdo=db();$pdo->beginTransaction();try{$s=$pdo->prepare('SELECT emp_id FROM employees WHERE emp_id=? FOR UPDATE');$s->execute([$id]);if(!$s->fetchColumn()){$pdo->rollBack();out(['error'=>'Employee not found'],404);}$pdo->prepare("UPDATE users SET status='Inactive',emp_id=NULL WHERE emp_id=?")->execute([$id]);$pdo->prepare('DELETE FROM employees WHERE emp_id=?')->execute([$id]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}log_action('DELETE_EMPLOYEE',(string)$id);out(['ok'=>true]);}
 }
 if($resource==='vacancies') {
   if($method==='GET'){ $public=($parts[1]??'')==='public';$sql='SELECT v.*,u.username created_by_name FROM vacancies v LEFT JOIN users u ON u.user_id=v.created_by';if($public)$sql.=" WHERE v.status='Published' AND v.deadline>=CURDATE()";$sql.=' ORDER BY v.created_at DESC';out(db()->query($sql)->fetchAll()); }
   if($method==='POST' && ($parts[1]??'')==='apply'){json_input_required($d,['vacancy_id','applicant_name','email']);$s=db()->prepare("SELECT vacancy_id FROM vacancies WHERE vacancy_id=? AND status='Published' AND deadline>=CURDATE()");$s->execute([(int)$d['vacancy_id']]);if(!$s->fetch())out(['error'=>'Vacancy is not accepting applications'],422);$s=db()->prepare('INSERT INTO applications(vacancy_id,applicant_name,email,phone) VALUES(?,?,?,?)');$s->execute([(int)$d['vacancy_id'],trim($d['applicant_name']),trim($d['email']),$d['phone']??null]);out(['app_id'=>(int)db()->lastInsertId()],201);}
   require_role(['Administrator','HR Officer']);if($method==='POST'){json_input_required($d,['title','requirements','deadline']);$s=db()->prepare('INSERT INTO vacancies(title,requirements,deadline,status,created_by) VALUES(?,?,?,?,?)');$s->execute([trim($d['title']),trim($d['requirements']),$d['deadline'],$d['status']??'Draft',$user['user_id']]);log_action('CREATE_VACANCY',$d['title']);out(['vacancy_id'=>(int)db()->lastInsertId()],201);}if($method==='PATCH'&&$id){$s=db()->prepare('UPDATE vacancies SET status=? WHERE vacancy_id=?');$s->execute([$d['status']??'Closed',$id]);out(['ok'=>true]);}
 }
 if($resource==='applications') {
   require_role(['Administrator','HR Officer']);if($method==='GET')out(db()->query('SELECT a.*,v.title vacancy_title FROM applications a JOIN vacancies v ON v.vacancy_id=a.vacancy_id ORDER BY a.created_at DESC')->fetchAll());if($method==='PATCH'&&$id){$status=$d['status']??'';if(!in_array($status,['Shortlisted','Rejected','Hired'],true))out(['error'=>'Invalid application status'],422);$s=db()->prepare('UPDATE applications SET status=? WHERE app_id=?');$s->execute([$status,$id]);out(['ok'=>true]);}
 }
 if($resource==='leave') {
   if($method==='GET') { $where='';$args=[];if($user['role']==='Employee'){$where=' WHERE l.emp_id=?';$args[]=$user['emp_id'];}elseif($user['role']==='Department Head'){$where=' WHERE l.status="Pending Head" OR l.emp_id IN (SELECT emp_id FROM employees WHERE dept_id=(SELECT dept_id FROM employees WHERE emp_id=?))';$args[]=$user['emp_id'];} $s=db()->prepare('SELECT l.*,e.full_name,e.staff_no FROM leave_requests l JOIN employees e ON e.emp_id=l.emp_id'.$where.' ORDER BY l.created_at DESC');$s->execute($args);out($s->fetchAll()); }
   if($method==='POST'){require_role(['Employee']);json_input_required($d,['type','start_date','end_date','reason']);if(!$user['emp_id'])out(['error'=>'This account is not linked to an employee'],422);$start=new DateTime($d['start_date']);$end=new DateTime($d['end_date']);if($end<$start)out(['error'=>'End date must not be before start date'],422);$days=(int)$start->diff($end)->days+1;if($d['type']==='Annual'){$s=db()->prepare('SELECT annual_leave_balance FROM employees WHERE emp_id=?');$s->execute([$user['emp_id']]);if((float)$s->fetchColumn()<$days)out(['error'=>'Insufficient annual leave balance'],422);}$s=db()->prepare('INSERT INTO leave_requests(emp_id,type,start_date,end_date,days,reason) VALUES(?,?,?,?,?,?)');$s->execute([$user['emp_id'],$d['type'],$d['start_date'],$d['end_date'],$days,trim($d['reason'])]);$leaveId=(int)db()->lastInsertId();$heads=db()->prepare("SELECT u.user_id FROM users u JOIN employees e ON e.emp_id=u.emp_id JOIN employees applicant ON applicant.dept_id=e.dept_id WHERE u.role='Department Head' AND applicant.emp_id=? AND u.status='Active'");$heads->execute([$user['emp_id']]);foreach($heads->fetchAll() as $h)notify_user((int)$h['user_id'],'A leave request needs your review.');notify_roles(['Administrator','HR Officer'],'An employee leave request is awaiting review.');log_action('APPLY_LEAVE',(string)$leaveId);out(['ok'=>true,'leave_id'=>$leaveId],201);}
   if($method==='POST' && ($parts[1]??'')==='decision') { /* use /leave/decision/{id} is not used; see PATCH below */ }
   if($method==='PATCH'&&$id){$s=db()->prepare('SELECT * FROM leave_requests WHERE leave_id=?');$s->execute([$id]);$leave=$s->fetch();if(!$leave)out(['error'=>'Leave request not found'],404);$decision=$d['decision']??'';$comment=trim($d['comment']??'');if($user['role']==='Department Head' && $leave['status']==='Pending Head'){$new=$decision==='approve'?'Pending HR':'Rejected';db()->prepare('UPDATE leave_requests SET status=?,head_comment=? WHERE leave_id=?')->execute([$new,$comment,$id]);$hr=db()->query("SELECT user_id FROM users WHERE role='HR Officer' AND status='Active'")->fetchAll();foreach($hr as $h)notify_user((int)$h['user_id'],'A leave request is waiting for HR review.');}
     elseif($user['role']==='HR Officer' && $leave['status']==='Pending HR') { if($decision==='approve'){ $pdo=db();$pdo->beginTransaction();try{if($leave['type']==='Annual'){$s=$pdo->prepare('UPDATE employees SET annual_leave_balance=annual_leave_balance-? WHERE emp_id=? AND annual_leave_balance>=?');$s->execute([$leave['days'],$leave['emp_id'],$leave['days']]);if(!$s->rowCount())throw new RuntimeException('Insufficient leave balance');}$pdo->prepare("UPDATE leave_requests SET status='Approved',hr_comment=? WHERE leave_id=?")->execute([$comment,$id]);$pdo->commit();}catch(Throwable $e){$pdo->rollBack();out(['error'=>$e->getMessage()],422);} } else {db()->prepare('UPDATE leave_requests SET status=\'Rejected\',hr_comment=? WHERE leave_id=?')->execute([$comment,$id]);} $s=db()->prepare('SELECT user_id FROM users WHERE emp_id=?');$s->execute([$leave['emp_id']]);$eid=$s->fetchColumn();if($eid)notify_user((int)$eid,'Your leave request was '.$decision.'d by HR.');}
     elseif($user['role']==='Administrator' && in_array($leave['status'],['Pending Head','Pending HR'],true)) {
       if(!in_array($decision,['approve','reject'],true))out(['error'=>'Decision must be approve or reject'],422);
       $pdo=db();$pdo->beginTransaction();
       try {
         if($decision==='approve' && $leave['type']==='Annual'){$s=$pdo->prepare('UPDATE employees SET annual_leave_balance=annual_leave_balance-? WHERE emp_id=? AND annual_leave_balance>=?');$s->execute([$leave['days'],$leave['emp_id'],$leave['days']]);if(!$s->rowCount())throw new RuntimeException('Insufficient leave balance');}
         $status=$decision==='approve'?'Approved':'Rejected';
         $pdo->prepare('UPDATE leave_requests SET status=?,hr_comment=? WHERE leave_id=?')->execute([$status,trim((string)($d['comment']??'')),$id]);
         $s=$pdo->prepare('SELECT user_id FROM users WHERE emp_id=?');$s->execute([$leave['emp_id']]);$employeeUser=$s->fetchColumn();
         if($employeeUser)notify_user((int)$employeeUser,'Your leave request was '.strtolower($status).' by an Administrator.');
         $pdo->commit();
       } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();out(['error'=>$e->getMessage()],422);}
     }
     else out(['error'=>'You cannot decide this request at its current stage'],403);log_action('LEAVE_DECISION',$id.':'.$decision);out(['ok'=>true]); }
 }
 if($resource==='attendance') {
   require_role(['Administrator','HR Officer','Department Head','Employee','Management']);if($method==='GET'){ $sql='SELECT a.*,e.full_name,e.staff_no FROM attendance a JOIN employees e ON e.emp_id=a.emp_id';$args=[];if($user['role']==='Employee'){$sql.=' WHERE a.emp_id=?';$args[]=$user['emp_id'];}$sql.=' ORDER BY attendance_date DESC LIMIT 500';$s=db()->prepare($sql);$s->execute($args);out($s->fetchAll());}if($method==='POST'){require_role(['Administrator','HR Officer']);json_input_required($d,['emp_id','attendance_date','status']);$s=db()->prepare('INSERT INTO attendance(emp_id,attendance_date,status,notes) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),notes=VALUES(notes)');$s->execute([(int)$d['emp_id'],$d['attendance_date'],$d['status'],$d['notes']??null]);out(['ok'=>true],201);}
 }
 if($resource==='payroll') {
   require_role(['Administrator','HR Officer','Employee','Management']);if($method==='GET'){ $sql='SELECT p.*,e.full_name,e.staff_no FROM payroll p JOIN employees e ON e.emp_id=p.emp_id';$args=[];if($user['role']==='Employee'){$sql.=' WHERE p.emp_id=?';$args[]=$user['emp_id'];}$sql.=' ORDER BY pay_month DESC';$s=db()->prepare($sql);$s->execute($args);out($s->fetchAll());}if($method==='POST'){require_role(['Administrator','HR Officer']);json_input_required($d,['emp_id','pay_month']);$s=db()->prepare('SELECT salary FROM employees WHERE emp_id=?');$s->execute([(int)$d['emp_id']]);$basic=$s->fetchColumn();if($basic===false)out(['error'=>'Employee not found'],404);$allow=(float)($d['allowance']??0);$tax=(float)($d['tax']??0);$pension=(float)($d['pension']??0);$other=(float)($d['other_deductions']??0);$net=(float)$basic+$allow-$tax-$pension-$other;if($net<0)out(['error'=>'Deductions cannot exceed gross salary'],422);$s=db()->prepare('INSERT INTO payroll(emp_id,pay_month,basic_salary,allowance,tax,pension,other_deductions,net_salary) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE basic_salary=VALUES(basic_salary),allowance=VALUES(allowance),tax=VALUES(tax),pension=VALUES(pension),other_deductions=VALUES(other_deductions),net_salary=VALUES(net_salary)');$s->execute([(int)$d['emp_id'],$d['pay_month'],$basic,$allow,$tax,$pension,$other,$net]);out(['net_salary'=>$net],201);}
 }
 if($resource==='evaluations') {
   if($method==='GET'){require_role(['Administrator','HR Officer','Department Head','Employee','Management']);$sql='SELECT v.*,e.full_name,e.staff_no FROM evaluations v JOIN employees e ON e.emp_id=v.emp_id';$args=[];if($user['role']==='Employee'){$sql.=' WHERE v.emp_id=?';$args[]=$user['emp_id'];}$sql.=' ORDER BY v.created_at DESC';$s=db()->prepare($sql);$s->execute($args);out($s->fetchAll());}if($method==='POST'){require_role(['Administrator','Department Head']);json_input_required($d,['emp_id','period','score']);$score=(float)$d['score'];if($score<0||$score>100)out(['error'=>'Score must be between 0 and 100'],422);$s=db()->prepare('INSERT INTO evaluations(emp_id,evaluator_id,period,score,comment) VALUES(?,?,?,?,?)');$s->execute([(int)$d['emp_id'],$user['user_id'],trim($d['period']),$score,$d['comment']??null]);out(['eval_id'=>(int)db()->lastInsertId()],201);}
 }
 if($resource==='notifications') {require_login();if($method==='GET'){$s=db()->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100');$s->execute([$user['user_id']]);out($s->fetchAll());}if($method==='PATCH'&&$id){$s=db()->prepare('UPDATE notifications SET is_read=1 WHERE not_id=? AND user_id=?');$s->execute([$id,$user['user_id']]);out(['ok'=>true]);}}
 if($resource==='reports'&&$method==='GET'){require_role(['Administrator','HR Officer','Management']);$pdo=db();out(['employees_by_department'=>$pdo->query('SELECT COALESCE(d.name,"Unassigned") department,COUNT(*) total FROM employees e LEFT JOIN departments d ON d.dept_id=e.dept_id GROUP BY d.name ORDER BY total DESC')->fetchAll(),'leave_by_status'=>$pdo->query('SELECT status,COUNT(*) total FROM leave_requests GROUP BY status')->fetchAll(),'attendance_by_status'=>$pdo->query('SELECT status,COUNT(*) total FROM attendance GROUP BY status')->fetchAll(),'payroll_monthly'=>$pdo->query('SELECT pay_month,COUNT(*) employees,SUM(net_salary) total_net FROM payroll GROUP BY pay_month ORDER BY pay_month DESC LIMIT 12')->fetchAll()]);}
 out(['error'=>'Endpoint not found'],404);
} catch(PDOException $e) { error_log($e->getMessage()); if(str_contains($e->getMessage(),'Duplicate entry')) out(['error'=>'A record with this unique value already exists'],409); out(['error'=>'Database operation failed'],500); } catch(Throwable $e){error_log($e->getMessage());out(['error'=>'Server error'],500);}
