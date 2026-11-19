<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('X-Content-Type-Options: nosniff');header('Cache-Control: no-store');
$root=__DIR__;$data=$root.'/data';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']),'httponly'=>true,'samesite'=>'Lax']);
session_start();
function out($a,$c=200){http_response_code($c);echo json_encode($a,JSON_UNESCAPED_UNICODE);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'||empty($_SERVER['HTTP_X_REQ']))out(['ok'=>false,'e'=>'bad request'],400);
function body(){$j=json_decode((string)file_get_contents('php://input'),true);return is_array($j)?$j:[];}
function adminf(){global $data;$f=$data.'/admin.json';return is_file($f)?json_decode((string)file_get_contents($f),true):null;}
function need(){if(empty($_SESSION['ok']))out(['ok'=>false,'e'=>'ابتدا وارد شوید'],401);}
function chk($p,$ad){return hash_equals((string)$ad['hash'],hash_pbkdf2('sha256',$p,(string)$ad['salt'],(int)$ad['iter'],64,false));}
function rl($key,$max,$win){global $data;$f=$data.'/rl.json';$fp=fopen($f,'c+');if(!$fp)return true;flock($fp,LOCK_EX);$d=json_decode((string)stream_get_contents($fp),true);if(!is_array($d))$d=[];$now=time();foreach($d as $k=>$v){$d[$k]=array_values(array_filter($v,function($t)use($now,$win){return $t>$now-$win;}));if(!$d[$k])unset($d[$k]);}$ok=count($d[$key]??[])<$max;if($ok)$d[$key][]=$now;ftruncate($fp,0);rewind($fp);fwrite($fp,json_encode($d));flock($fp,LOCK_UN);fclose($fp);return $ok;}
function bk_rw($fn){global $data;$f=$data.'/bookings.json';$fp=fopen($f,'c+');flock($fp,LOCK_EX);$d=json_decode((string)stream_get_contents($fp),true);if(!is_array($d))$d=[];$d=$fn($d);ftruncate($fp,0);rewind($fp);fwrite($fp,json_encode($d,JSON_UNESCAPED_UNICODE));flock($fp,LOCK_UN);fclose($fp);return $d;}
$a=$_GET['a']??'';
switch($a){
case 'me':need();out(['ok'=>true]);
case 'login':{$ip=hash('sha256',$_SERVER['REMOTE_ADDR']??'');if(!rl('login'.$ip,10,900))out(['ok'=>false,'e'=>'تعداد تلاش زیاد است. کمی بعد دوباره امتحان کنید'],429);
 $b=body();$ad=adminf();$ok=$ad&&isset($b['u'],$b['p'])&&hash_equals((string)$ad['user'],(string)$b['u'])&&chk((string)$b['p'],$ad);
 if(!$ok){sleep(1);out(['ok'=>false,'e'=>'نام کاربری یا رمز اشتباه است'],401);}session_regenerate_id(true);$_SESSION['ok']=1;out(['ok'=>true]);}
case 'logout':$_SESSION=[];session_destroy();out(['ok'=>true]);
case 'save':{need();$b=body();$c=$b['cfg']??null;if(!is_array($c))out(['ok'=>false,'e'=>'داده نامعتبر'],400);$j=json_encode($c,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);if(strlen($j)>2000000)out(['ok'=>false,'e'=>'حجم زیاد است'],400);
 $f=$root.'/config.json';if(is_file($f))@copy($f,$data.'/config.bak.json');if(file_put_contents($f,$j,LOCK_EX)===false)out(['ok'=>false,'e'=>'نوشتن روی config.json ممکن نیست؛ دسترسی فایل را بررسی کنید'],500);out(['ok'=>true]);}
case 'upload':{need();$f=$_FILES['file']??null;if(!$f||$f['error']!==UPLOAD_ERR_OK)out(['ok'=>false,'e'=>'آپلود ناموفق'],400);if($f['size']>8*1024*1024)out(['ok'=>false,'e'=>'حجم بیش از ۸ مگابایت'],400);
 $i=@getimagesize($f['tmp_name']);$map=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];if(!$i||!isset($map[$i['mime']]))out(['ok'=>false,'e'=>'فقط JPG، PNG، WebP یا GIF'],400);
 $n=bin2hex(random_bytes(8)).'.'.$map[$i['mime']];if(!move_uploaded_file($f['tmp_name'],$root.'/uploads/'.$n))out(['ok'=>false,'e'=>'ذخیره ممکن نیست؛ دسترسی پوشه uploads را بررسی کنید'],500);out(['ok'=>true,'path'=>'uploads/'.$n]);}
case 'bookings':need();$d=array_reverse(bk_rw(function($x){return $x;}));out(['ok'=>true,'list'=>$d]);
case 'bk_set':{need();$b=body();$id=(string)($b['id']??'');$act=(string)($b['act']??'');bk_rw(function($d)use($id,$act){$o=[];foreach($d as $r){if(($r['id']??'')===$id){if($act==='del')continue;if($act==='done')$r['status']='done';}$o[]=$r;}return $o;});out(['ok'=>true]);}
case 'passwd':{need();$b=body();$ad=adminf();if(!$ad||!chk((string)($b['o']??''),$ad))out(['ok'=>false,'e'=>'رمز فعلی اشتباه است'],401);$n=(string)($b['n']??'');if(strlen($n)<10)out(['ok'=>false,'e'=>'رمز جدید حداقل ۱۰ کاراکتر باشد'],400);
 $u=trim((string)($b['u']??''));if($u==='')$u=(string)$ad['user'];$salt=bin2hex(random_bytes(8));file_put_contents($data.'/admin.json',json_encode(['user'=>$u,'salt'=>$salt,'iter'=>100000,'hash'=>hash_pbkdf2('sha256',$n,$salt,100000,64,false)]),LOCK_EX);out(['ok'=>true]);}
default:out(['ok'=>false,'e'=>'unknown'],404);}
