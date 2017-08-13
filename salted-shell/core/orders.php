<?php


function create_order_from_user($order_submitter,$order_type,$goods_id,$delivery_fee,$purchase_amount,$single_cost,$offer){
    global $db_host;
    global $db_pass;
    global $db_name;
    global $db_user;
    global $db_order_table;
    global $db_goods_table;

    $link = mysqli_connect($db_host,$db_user,$db_pass,$db_name);
    $sql = "SELECT remain FROM $db_goods_table WHERE goods_id='$goods_id'";
    $result = $link->query($sql);
    if($result){
        if(mysqli_fetch_assoc($result)['remain'] < $purchase_amount){
            die(generate_error_report("No enough goods"));
        }
    }else{
        die(generate_error_report("No such goods"));
    }
    $sql = "INSERT INTO $db_order_table (goods_id, order_type,order_submitter, delivery_fee, purchase_amount, single_cost, offer, order_status) 
            VALUE 
            ('$goods_id','$order_type','$order_submitter','$delivery_fee','$purchase_amount','$single_cost','$offer','waiting')";
    var_dump($order_type);
    $result = $link->query($sql);
    $insert_id = $link->insert_id;
    $link->commit();
    if ($result){
        $link->close();
        return $insert_id;
    }else{
        die(generate_error_report($link->error));
    }
}
function create_order($session_key,$order_type,$goods_id,$delivery_fee,$purchase_amount,$single_cost,$offer){
    if($student_id = get_student_id_from_session_key($session_key)){
        $result = create_order_from_user($student_id,$order_type,$goods_id,$delivery_fee,$purchase_amount,$single_cost,$offer);
        if($result){
            post_create_order();
            return $result;
        }else{
            return false;
        }
    }else{
        return false;
    }
}


function cancel_order_($order_id){
    global $db_host;
    global $db_pass;
    global $db_name;
    global $db_user;
    global $db_order_table;
    
    

    if($result) return $order_id;
    else        return false;
}
function cancel_order_from_user($current, $order_id){
    global $db_host;
    global $db_pass;
    global $db_name;
    global $db_user;
    global $db_order_table;

    $link = mysqli_connect($db_host,$db_user,$db_pass,$db_name);
    $sql = "SELECT goods_id, order_submitter,purchase_amount from $db_order_table where order_id = '$order_id'";
    $result = $link->query($sql);
    if($result && $result->num_rows != 0){
        $result = mysqli_fetch_assoc($result);
        $goods_id = $result['goods_id'];
        $user_id  = $result['user_id'];
        $purchase_amount = $result['purchase_amount'];
        if($current == $user_id || $current == fetch_goods_submitter($goods_id)){
            $sql = "DELETE FROM $db_order_table WHERE order_id = '$order_id'";
            $result = $link->query($sql);
            $link->commit();
            $link->close();
            if($result){
                increase_goods_remain($goods_id, $purchase_amount);
                post_cancel_order();
                return json_encode(array(
                    "status" => "success"
                ));
            }else{
                return generate_error_report("Unknown database error at cancel_order_from_user");
            }
        }else{
            return generate_error_report("Access dined");
        }
    }else{
        return generate_error_report("No such order");
    }
}

function cancel_order($session_key,$order_id){
    $current = get_student_id_from_session_key($session_key);
    if(!$current){
        return false;
    }
    return cancel_order_from_user($current, $order_id);
}

function accept_order_from_user($user_id, $order_id){
    global $db_host;
    global $db_pass;
    global $db_name;
    global $db_user;
    global $db_goods_table;
    global $db_order_table;

    $link = mysqli_connect($db_host,$db_user,$db_pass,$db_name);
    $sql = "SELECT goods_id,purchase_amount FROM $db_order_table WHERE order_id='$order_id'";
    $result = $link->query($sql);
    if($result && $result->num_rows != 0){
        $result = mysqli_fetch_assoc($result);
        $goods_id = $result['goods_id'];
        $purchase_amount = $result['purchase_amount'];
        $sql = "SELECT submitter,remain FROM $db_goods_table WHERE goods_id='$goods_id'";
        $result = $link->query($sql);
        if($result && $result->num_rows != 0){
            $result = mysqli_fetch_assoc($result);
            if("".$result['submitter'] == "".$user_id){
                if($result['remain'] < $purchase_amount){
                    $link->close();
                    die(generate_error_report("Not enouge goods"));
                }
                $sql = "UPDATE $db_order_table SET status='accepted' WHERE order_id='$order_id'";
                $result = $link->query($sql);
                $link->commit();
                if(!$result){
                    var_dump($link->error);
                    $link->close();
                    die(generate_error_report("Unknonw error"));
                }
                decrease_goods_remain($goods_id,$purchase_amount);
                $link->close();
                post_accept_order();
                die(json_encode(array(
                    "status" => "success",
                    "order_id" => $order_id,
                    "order_status" => "accepted"
                )));
            }else{
                $link->close();
                die(generate_error_report("You have no access to accept this order"));
            }
        }else{
            die(generate_error_report("There's order but no goods??"));
        }
    }else{
        $link->close();
        die(generate_error_report("No such order"));
    }
}

function complete_order_from_user($student_id, $order_id){      // 卖方完成义务
    global $db_host;
    global $db_pass;
    global $db_name;
    global $db_user;
    global $db_order_table;
    
    $link = mysqli_connect($db_host,$db_user,$db_pass,$db_name);
    $sql = "SELECT goods_id, status from $db_order_table where order_id = '$order_id'";
    $result = $link->query($sql);
    $link->close();
    if($result){
        $result = mysqli_fetch_assoc($result);
        $goods_id = $result['goods_id'];
        if(fetch_goods_submitter($goods_id) != $student_id){
            die(generate_error_report("This order is not yours"));
        }elseif($result['status'] == "waiting"){
            die(generate_error_report("You haven't accepted this order"));
        }elseif($result['status'] == "completed" || $result['status'] == "finished"){
            die(generate_error_report("This order had been completed already"));
        }
        $status = change_order_staus($order_id, "completed");
        if(!$status)
            die(generate_error_report("Unknown error with database"));
        post_complete_order();
        return json_encode(array(
            "status" => "success",
            "order_id" => "$order_id",
            "order_status" => "completed"
        ));
    }else{
        die(generate_error_report("Unknown error as complete_order_from_use"));
    }
}

function finish_order_from_user($student_id, $order_id){
    global $db_host;
    global $db_pass;
    global $db_name;
    global $db_user;
    global $db_order_table;
    
    $link = mysqli_connect($db_host,$db_user,$db_pass,$db_name);
    $sql = "SELECT order_submitter,status from $db_order_table where order_id = '$order_id'";
    $result = $link->query($sql);
    $link->close();
    if($result){
        $result = mysqli_fetch_assoc($result);
        $user_id  = $result['order_submitter'];
        if($user_id != $student_id){
            die(generate_error_report("This order is not yours"));
        }elseif($result['status'] == "waiting" || $result['status'] == "accepted"){
            die(generate_error_report("Seller haven't completed this order yet!"));
        }elseif($result['status'] == "finished") {
            die(generate_error_report("This order had been finished already"));
        }
        $status = change_order_staus($order_id, "finished");
        if(!$status)
            die(generate_error_report("Unknown error with database"));
        post_complete_order();
        return json_encode(array(
            "status" => "success",
            "order_id" => "$order_id",
            "order_status" => "finished"
        ));
    }
}

function change_order_status($order_id, $status){
    global $db_host;
    global $db_pass;
    global $db_name;
    global $db_user;
    global $db_order_table;

    $link = mysqli_connect($db_host,$db_user,$db_pass,$db_name);
    $sql = "UPDATE  $db_order_table SET status='$status' WHERE order_id='$order_id'";
    $result = $link->query($sql);
    $link->close();
    if($result)
        return true;
    else
        return false;
}

function list_orders_from_user($user_id, $filters=[],$page=1, $limit=10){
    global $db_host;
    global $db_pass;
    global $db_name;
    global $db_user;
    global $db_order_table;
    global $db_goods_table;

    $link = mysqli_connect($db_host,$db_user,$db_pass,$db_name);
    $sql = "SELECT * from $db_goods_table RIGHT OUTER JOIN $db_order_table on $db_order_table"."."."goods_id=$db_goods_table"."."."goods_id";
    $filter_str = "";
    foreach($filters as $key=>$value){
        if($filter_str == "")
            $filter_str = " WHERE";
        else
            $filter_str." AND ";
        $filter_str = $filter_str." ".$key."='".$value."'";
    }
    if($filter_str == "")
        $filter_str = " WHERE";
    else
        $filter_str = $filter_str." AND";
    $base = ($page-1)*$limit;
    $sql = $sql.$filter_str;
    $sql = $sql." order_submitter='$user_id'";
    $sql = $sql." LIMIT $base, $limit";   
    $results = $link->query($sql);
    $return_var = array(
        "status" => "success",
        "orders" => array()
    );
    $link->close();
    if($results){
        while($result = mysqli_fetch_assoc($results))
            $return_var["orders"][] = $result;        // append a new array at the end of this array
        $return_var['count'] = count($return_var['orders']);
        return json_encode($return_var);
    }else{
        die(generate_error_report("Database Error as list_order_from_user()"));
    }
}

function post_create_order(){

}
function post_cancel_order(){

}
function post_complete_order(){

}
function post_accept_order(){

}
?>