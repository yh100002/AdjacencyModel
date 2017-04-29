<?php


$servername = "localhost";
$username = "root";
$password = "";
$dbname = "yh100002";

$conn = mysqli_connect($servername, $username, $password, $dbname) or die("Connection failed: " . mysqli_connect_error());
/* check connection */
if (mysqli_connect_errno()) {
    printf("Connect failed: %s\n", mysqli_connect_error());
    exit();
}

if(isset($_GET['operation'])) {
  try {
    $result = null;
    switch($_GET['operation']) {
      case 'get_node2':    
        $result = [];
        break;
      case 'get_node':
        $node = isset($_GET['id']) && $_GET['id'] !== '#' ? (int)$_GET['id'] : 0;
        $sql = "SELECT id,title as text,parent_id,status FROM `tasks` ORDER BY parent_id ";
        $data = [];
        $res = mysqli_query($conn, $sql) or die("database error:". mysqli_error($conn));
        if(mysqli_num_rows($res) > 0)
        {
            while( $row = mysqli_fetch_assoc($res) ) { 
             $data[] = $row;
            }
        }
        else $data = [];
        
        $itemsByReference = array();
        
        foreach($data as $key => &$item) {
           $itemsByReference[$item['id']] = &$item;           
           // Children array:
           $itemsByReference[$item['id']]['children'] = array();
           // Empty data class (so that json_encode adds "data: {}" )           
           
           if($item['status'] == 0) 
           {
               $itemsByReference[$item['id']]['state']['selected'] = false;                              
           }
           else if($item['status'] == 1)
           {
                $itemsByReference[$item['id']]['state']['selected'] = true;                               
           }
           else if($item['status'] == 2)
           {
                $itemsByReference[$item['id']]['state']['selected'] = true;                                              
           }
           $itemsByReference[$item['id']]['data']['status'] = $item['status'];
           $itemsByReference[$item['id']]['data']['description'] = makeDescription($conn,$item['id']);
        }

        // Set items as children of the relevant parent item.
        foreach($data as $key => &$item)
           if($item['parent_id'] && isset($itemsByReference[$item['parent_id']]))
            $itemsByReference [$item['parent_id']]['children'][] = &$item;

        // Remove items that were added to parents elsewhere:
        foreach($data as $key => &$item) {
           if($item['parent_id'] && isset($itemsByReference[$item['parent_id']]))
            unset($data[$key]);
        }
        
        $result = $data;    
        sort($result);  
        
        if($result == null) $result = [];
        
        break;
      case 'create_node':
        $node = isset($_GET['id']) && $_GET['id'] !== '#' ? (int)$_GET['id'] : 0;        
        $nodeText = isset($_GET['text']) && $_GET['text'] !== '' ? $_GET['text'] : '';        
        $sql ="INSERT INTO `tasks` (`title`, `parent_id`) VALUES('".$nodeText."', '".$node."')";
        mysqli_query($conn, $sql);
        
        $result = array('id' => mysqli_insert_id($conn));
        
        CalcChildrenStatus($conn,$node,1);
        CalcParentsStatus($conn,$node,1);  

        break;
      case 'rename_node':
        $node = isset($_GET['id']) && $_GET['id'] !== '#' ? (int)$_GET['id'] : 0;        
        $nodeText = isset($_GET['text']) && $_GET['text'] !== '' ? $_GET['text'] : '';        
        $sql ="UPDATE `tasks` SET `title`='".$nodeText."' WHERE `id`= '".$node."'";
        mysqli_query($conn, $sql);
        break;
      case 'delete_node':
        $node = isset($_GET['id']) && $_GET['id'] !== '#' ? (int)$_GET['id'] : 0;
        $sql ="DELETE FROM `tasks` WHERE `id`= '".$node."'";
        mysqli_query($conn, $sql);
        break;
      case 'update_status':
        $node = isset($_GET['id']) && $_GET['id'] !== '#' ? (int)$_GET['id'] : 0;
        $status = $_GET['status'];   
        $prev = $_GET['prev'];         
                
        CalcChildrenStatus($conn,$node,$status);
        CalcParentsStatus($conn,$node,$status);          
       
        exit();
        break;
      case 'dnd':
        $id = isset($_GET['id']) && $_GET['id'] !== '#' ? (int)$_GET['id'] : 0;
        $parent_id = isset($_GET['parent_id']) && $_GET['parent_id'] !== '#' ? (int)$_GET['parent_id'] : 0;
        $status = $_GET['status'];   
      
        $sql = "UPDATE `tasks` SET `status`='" .$status."',parent_id='" . $parent_id . "' WHERE  `id`=" . $id;
        mysqli_query($conn, $sql);
        
        CalcChildrenStatus($conn,$id,1);
        CalcParentsStatus($conn,$id,1);  
      
        exit();
        break;
      default:
        throw new Exception('Unsupported operation: ' . $_GET['operation']);
        break;
    }   
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result);
  }
  catch (Exception $e) {
    header($_SERVER["SERVER_PROTOCOL"] . ' 500 Server Error');
    header('Status:  500 Server Error');
    echo $e->getMessage();
  }
  die();
}

function allChildrenNode($conn,$id)
{  
    $s = "SELECT * FROM tasks WHERE parent_id =".$id;    
    $r = mysqli_query($conn,$s);
    $children = array();
    if(mysqli_num_rows($r) > 0)
    {       
        while($row = mysqli_fetch_assoc($r))
        { 
            $children[$row['id']] = $row;
            $arr = allChildrenNode($conn,$row['id']);
            if(count($arr) > 0)
            {
                //$children[$row['id']]['children'] = array();
                $children[$row['id']]['children'] = array_values(allChildrenNode($conn,$row['id']));
            } 
        }
    }
    return $children;
}

function breadCrumb($conn,$id) 
{
    // look up the parent of this node 
    $result = mysqli_query($conn,"SELECT c1.parent_id,c2.title as text,c2.status FROM tasks AS c1
                                LEFT JOIN tasks AS c2 ON c1.parent_id=c2.id 
                                WHERE c1.id='$id'");  
    // save the path in this array
    $row = mysqli_fetch_assoc($result) ;
    $path = array();
        //continue if this node is not the root node
    if ($row['parent_id']!=NULL) 
    {
        // the last part of the path to node 
        end($path);
        $last_key = key($path);
        $key = $last_key==0 ? 0 : $last_key+1;

        $path[$key][0] = $row['parent_id'];
        //$path[$key]['id'] = $row['parent_id'];
        //$path[$key]['text'] = $row['text'];
        $path[$key][1] = $row['status'];
        //echo "status=>" . $path[$key]['status'];
        $path = array_merge(breadCrumb($conn,$row['parent_id']), $path);
        rsort($path);
        //$path = breadCrumb($conn,$row['parent_id']);
    }        
   return $path;
}


$total_dependency_count;
$total_done_count;
$total_progress_count;
$total_complete_count;

function CalcCounting($value,$key)
{
    global $total_dependency_count;
    global $total_done_count;
    global $total_progress_count;  
    global $total_complete_count;
    
    if($key == "id") $total_dependency_count++;
    if($key == "status" && $value == 0) $total_progress_count++;
    if($key == "status" && $value == 1) $total_done_count++;  
    if($key == "status" && $value == 2) $total_complete_count++;  
    
}

function CalcParentsStatus($conn,$id,$my_request_status)
{
    global $total_dependency_count;
    global $total_done_count;
    global $total_progress_count;
    global $total_complete_count;
   
    $parents = breadCrumb($conn,$id);
    //print_r($parents);
    foreach ($parents as $key => $value) 
    {
        $total_dependency_count = 0;
        $total_done_count = 0;
        $total_progress_count = 0;
        $total_complete_count = 0;    
        $parent_id = $value[0];        
        $parent_status = $value[1];
        if($value[1] == NULL) 
        {
            $parent_status = 0;
        }
      
        array_walk_recursive(array_values(allChildrenNode($conn,$parent_id)),"CalcCounting"); 
        $status = 0;   
      
        if($my_request_status == 0)
        {   
            if($parent_status == 1 || $parent_status == 2)
            {
                echo "if==>" . $parent_id;
                $status = 1;                
            }
            else
            {
                echo "if==>" . $parent_id;
                $status = 0;                
            }            
        }
        else if($my_request_status == 1)
        {          
            if($total_done_count == $total_complete_count)
            {          
                if($total_progress_count == 0)
                {                     
                    $status = 2;                
                }
                else
                {
                    $status = 1;                                    
                }
            }
            else
            {
                if($parent_status == 1 || $parent_status == 2)
                {
                    if($total_dependency_count == $total_complete_count)
                    {
                        $status = 2;
                    }
                    else
                    {
                        $status = 1;         
                    }       
                }
                else
                {
                     $status = 0;
                }                     
            }            
        }        
     
        $sql = "UPDATE `tasks` SET `status`='" .$status."' WHERE  `id`=" . $parent_id;
        mysqli_query($conn, $sql);         
    }   
    
     
}

function CalcChildrenStatus($conn,$id,$my_request_status)
{
    global $total_dependency_count;
    global $total_done_count;
    global $total_progress_count;
    global $total_complete_count;
    
    $total_dependency_count = 0;
    $total_done_count = 0;
    $total_progress_count = 0;
    $total_complete_count = 0;    
   
    array_walk_recursive(array_values(allChildrenNode($conn,$id)),"CalcCounting"); 
    $status = 0;
  
    if($my_request_status == 0)
    {  
        $status = 0;
    }
    else if($my_request_status == 1)
    {
        if($total_done_count == $total_complete_count)
        {         
            if($total_progress_count == 0)
            {
                $status = 2;                
            }
            else
            {
                $status = 1;
            }
        }
        else
        {        
            if($total_dependency_count == $total_complete_count)
            {
                $status = 2;
            }
            else
            {
                $status = 1;                            
            }            
        }            
    }  
    $sql = "UPDATE `tasks` SET `status`='" .$status."' WHERE  `id`=" . $id;
    mysqli_query($conn, $sql);      
}

function makeDescription($conn,$id)
{
    global $total_dependency_count;
    global $total_done_count;
    global $total_progress_count;
    global $total_complete_count;
    
    $total_dependency_count = 0;
    $total_done_count = 0;
    $total_progress_count = 0;
    $total_complete_count = 0;
    
    array_walk_recursive(array_values(allChildrenNode($conn,$id)),"CalcCounting");    
    
    return "Total Children : " . $total_dependency_count . " DONE : " . $total_done_count . " IN PROGRESS : " . $total_progress_count ." COMPLETED : ". $total_complete_count;
    
}