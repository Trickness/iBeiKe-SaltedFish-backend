<?php
class Controller{
    private $config = null;

    function __construct($config){
        $this->config = $config;
    }

    function connect($name){
        $host = $this->config['db_host'];
        $user = $this->config['db_user'];
        $pass = $this->config['db_pass'];
        return mysqli_connect($host,$user,$pass,$name);
    }

    function check($username,$password){
        $link = $this->connect($this->config['db_1']['name']);
	$link->query("set names 'utf8'");
        $table = $this->config['db_1']['table'];
        // $query = $link->prepare("SELECT password,salt FROM $table WHERE username = ?");
        //$query->bind_param('s',$username);
        //$query->execute();
        //$result = mysqli_fetch_assoc($query->get_result());
        $query = mysqli_query($link,"SELECT password,salt FROM $table WHERE username = '$username'");
	$result = mysqli_fetch_assoc($query);
	mysqli_close($link);
        
        $user_pass = md5(md5($password).$result['salt']);
        $return = array();
        if (isset($result)) {
            if ($user_pass == $result['password']) {
                $return['code'] = 0;
                $return['msg'] = '验证成功';
            }else{
                $return['code'] = 1;
                $return['msg'] = '密码错误';
            }
        }else {
            $return['code'] = 2;
            $return['msg'] = '用户不存在';
        }
        return $return;
    }

    function getRank($level = 3){
        $rank = array();
        $link = $this->connect($this->config['db_2']['name']);
        $table = $this->config['db_2']['table'];
        // $query = $link->prepare("SELECT username,score FROM $table WHERE lvl = ? ORDER BY score DESC LIMIT 0,5");
        // $query->bind_param('s',$level);
        // $query->execute();
        // $result = $query->get_result();
	$result = mysqli_query($link,"SELECT username,score FROM $table WHERE lvl = '$level' ORDER BY score DESC LIMIT 0,5");
        while ($res = mysqli_fetch_assoc($result)) {
            $rank[] = $res;
        }
        mysqli_close($link);
        return $rank;
    }

    function updateRank($username,$score,$lvl){
        $link = $this->connect($this->config['db_2']['name']);
        $table = $this->config['db_2']['table'];

        // $check = $link->prepare("SELECT COUNT(*) as num,score FROM $table WHERE username = ?");
        // $check->bind_param('s',$username);
        // $check->execute();
        // $count = mysqli_fetch_assoc($check->get_result());
        $query = mysqli_query($link,"SELECT COUNT(*) as num,score FROM $table WHERE username = '$username'");
	$count = mysqli_fetch_assoc($query);	

	if ($count['num']==0) {
            // $query = $link->prepare("INSERT INTO $table(username,score,lvl) VALUES (?,?,?)");
            // $query->bind_param('sss',$username,$score,$lvl);
            // $query->execute();
            // $result = $query;
            $result = mysqli_query($link,"INSERT INTO $table(username,score,lvl) VALUES ('$username','$score','$lvl')");
	    
	    mysqli_close($link);
            return $result->affected_rows;
        }else if($count['score']<$score && $score>4){
            // $query = $link->prepare("UPDATE $table SET score = ? WHERE username = ? AND lvl = ?");
            // $query->bind_param('sss',$score,$username,$lvl);
            // $query->execute();
            // $result = $query;
	    $result = mysqli_query($link,"UPDATE $table SET score = '$score' WHERE username = '$username' AND lvl = '$lvl'");

	    mysqli_close($link);
            return $result->affected_rows;
        }else{
            mysqli_close($link);
            return 0;
        }
    }
}

// require_once "./config.php";
// $ctl = new Controller($config);
// // var_dump($ctl->check('ab','000000'));
// var_dump($ctl->getRank());
// var_dump($ctl->updateRank('nonono',23,3));
?>
