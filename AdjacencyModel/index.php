<html>
	<head>
		<title>Realtime Adjacency Model with jsTree & WebSocket & PHP by Y.H.SON </title>
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.2.1/themes/default/style.min.css" />
		<script type='text/javascript' src='http://code.jquery.com/jquery-2.1.0.js'></script>		
                <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">        
                <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script> 
		<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.3/jstree.min.js"></script>
                <script type="text/javascript" src="jstreegrid.js"></script>
                <script src="clientwebsocket.js"></script>
		<style type="text/css">
			@import url('http://getbootstrap.com/dist/css/bootstrap.css');
                        input, textarea {border:1px solid #CCC;margin:0px;padding:0px}                        
                        #log {width:50%;height:100px;display: none;}
                        #message {width:50%;line-height:20px;display: none;}
		</style>
		<script type="text/javascript">
                        var msgServer;                       
                        
			$(document).ready(function(){
                            InitWS();
                            InitTree();
			});
                        
                        function InitWS()
                        {
                            log('Connecting...');
                            msgServer = new FancyWebSocket('ws://127.0.0.1:9300');

                            $('#message').keypress(function(e) {
                                    if ( e.keyCode == 13 && this.value ) {
                                            log( 'You: ' + this.value );
                                            send( this.value );

                                            $(this).val('');
                                    }
                            });

                            //Let the user know we're connected
                            msgServer.bind('open', function() {
                                    log( "Connected." );
                            });
                           
                            msgServer.bind('close', function( data ) {
                                    log( "Disconnected." );
                            });

                            //Log any messages sent from server
                            msgServer.bind('message', function( payload ) {
                                    log( payload );
                                    //alert(payload);
                                    if(payload == 'push_broadcasting_refresh')
                                    {
                                        InitOverall();
                                    }
                            });

                            msgServer.connect();
                        };
                        
                        function log( text ) {
                                $log = $('#log');
                                //Add text to log
                                $log.append(($log.val()?"\n":'')+text);
                                //Autoscroll
                                $log[0].scrollTop = $log[0].scrollHeight - $log[0].clientHeight;
                        };
                        
                        function send( text ) {
                                msgServer.send( 'message', text );
                        };
                        
                        function InitTree()
                        {
                            $("div#jstree").bind("loaded_grid.jstree",function(){
					$("span#status").text("loaded");
				}).on("select_cell.jstree-grid",function (e,data) {
					$("span#clicked").html("clicked "+data.column+" of value "+data.value);
				}).on('update_cell.jstree-grid',function (e,data) {
					$("span#changed").html("changed "+data.col+" from "+data.old+" to "+data.value);
				});


				$("div#jstree").jstree({
					plugins: ["themes","json","grid","dnd","contextmenu","checkbox","search","sort"],
					core: {                                            
                                                "check_callback": true,		
                                                //'data':[],
                                                
                                                'data': {
                                                            'url' : 'response.php?operation=get_node',
                                                            'data' : function (node) { return { 'id' : node.id }; },
                                                            "dataType" : "json"
                                                          },
                                                "multiple" : false,
                                                "animation" : 0
					},
                                         checkbox : {
                                            "whole_node" : true, 
                                            "keep_selected_style" : false, 
                                            "three_state" : false,
                                            "tie_selection": true
                                        },
					grid: {
						columns: [
							{width: 500, header: "Nodes",title:"_DATA_"},
							{width: 150, cellClass: "col1", value: function(node){if(node.data.status == 0){return "IN PROGRESS";}if(node.data.status == 1){return "DONE";}if(node.data.status == 2){return "COMPLETE";}},header: "<i>Status</i>", title:"status", valueClass:"spanclass"},
                                                        {width: 800, cellClass: "col2", value: "description", header: "<i>Description</i>", title:"description", valueClass:"spanclass"}
						],
						resizable:true,
						draggable:true,
						contextmenu:true,						
						width: 1920,
						height: 768
					},
					dnd: {
						drop_finish : function (){                                                   
						},
						drag_finish : function () {
						},
						drag_check : function (data) {
						return {
							after : true,
							before : true,
							inside : true
						};
						}
					},
                                        contextmenu: {
                                                "items": function ($node) {
                                                    return {
                                                        "Create": {
                                                            "label": "Create a new node",
                                                            "action": function (obj) {
                                                                //this.create(obj);
                                                                //alert($node.id);
                                                                var newnode = {"id":0,"text":"New Node","parent":"#"+$node.id,"data":{"status":"0"}};
                                                                //alert(newnode.parent);
                                                                createNode("#"+$node.id, newnode, "first");     
                                                            }
                                                        },
                                                        "Rename": {
                                                            "label": "Rename an node",
                                                            "action": function (obj) {
                                                                //this.rename(obj);
                                                                var tree = $("div#jstree").jstree(true);
                                                                tree.edit($node);
                                                            }
                                                        }                                                        
                                                    };
                                                }
                                            }
				})
				.on('loaded.jstree', function() {
                                        //alert('loaded');
					$("div#jstree").jstree('open_all');
				})
                               .on('create_node.jstree', function (e, data) {                                       
                                        $.get('response.php?operation=create_node', { 'id' : data.node.parent, 'position' : data.position, 'text' : data.node.text})
                                          .done(function (d) {                                             
                                            data.instance.set_id(data.node, d.id); 
                                            //data.instance.refresh();
                                          })
                                          .fail(function () {
                                            data.instance.refresh();
                                          });
                                })
                                .on('rename_node.jstree', function (e, data) {
                                    $.get('response.php?operation=rename_node', { 'id' : data.node.id, 'text' : data.text })
                                      .fail(function () {
                                        data.instance.refresh();
                                      });
                                      send('push_broadcasting_refresh');
                                })
                                .on('delete_node.jstree', function (e, data) {
                                    $.get('response.php?operation=delete_node', { 'id' : data.node.id })
                                      .fail(function () {
                                        data.instance.refresh();
                                      });
                                })
                                .on("select_node.jstree", function (e, data) { 
                                    //alert(data.node.id);
                                    UpdateStateCommand('response.php?operation=update_status',data.node.id,1,data.node.data.status);
                                    send('push_broadcasting_refresh');    
                                })
                                .on("deselect_node.jstree", function(e, data) {
                                   UpdateStateCommand('response.php?operation=update_status',data.node.id,0,data.node.data.status);
                                   send('push_broadcasting_refresh');
                                })
                                .on("move_node.jstree", function (e, data) {                                                                 
                                    //alert("moving node id " + data.node.id);  
                                    //console.log(data);
                                    DndCommand('response.php?operation=dnd',data.node.id,data.node.parent,data.node.data.status);
                                    $("div#jstree").jstree('open_all');
                                    send('push_broadcasting_refresh');    
                                });				
                                
				$("input#search").keyup(function (e) {
					var tree = $("div#jstree").jstree();
					tree.search($(this).val());
				});
                        };
                        
                        function DeInintTree()
                        {
                            $("div#jstree").jstree('destroy');
                        };
                        
                        function UpdateStateCommand(cmd,id,val,prev)
                        {               
                            $.get(cmd, { 'id' : id, 'status':val , 'prev':prev})
                            .done(function (data) {
                                //alert(data);                               
                               InitOverall();
                            });           
                        };
                        
                        function DndCommand(cmd,id,parentid,status)
                        {               
                            $.get(cmd, { 'id' : id, 'parent_id':parentid , 'status':status})
                            .done(function (data) {
                               //alert(data);                               
                               InitOverall();
                            });           
                        };
                        
                        function InitOverall()
                        {
                             DeInintTree();
                             InitTree();
                        };
                        
                        function createNode(parent_node, newNode, position) 
                        {   
                            var inst = $('div#jstree').jstree(true);
                            var root = inst.get_node(parent_node);
                            var node_id = inst.create_node(root,newNode);                   
                            var parent = inst.get_node(node_id);
                            //alert(node_id);
                            //$("div#jstree").jstree("open_all");                           
                            InitOverall();
                        };    

                        function InitParentList()
                        {
                            var treeData = $('div#jstree').jstree(true).get_json('#', {flat:true})                
                            var jsonData1 = JSON.stringify(treeData );                
                            var jsonData2 = JSON.parse(jsonData1);                
                            var options = []; 
                            options.push("<option value='0'>root</option>");
                            for (var key in jsonData2) {
                             if (jsonData2.hasOwnProperty(key)) {
                                options.push("<option value='" + jsonData2[key].id + "'>" + jsonData2[key].text + "</option>");
                             }
                            }               
                            $('#parentlist').append(options.join("")).selectmenu();              
                        };
            
                        $( function() {
                            var dialog, form,             
                              tips = $( ".validateTips" );
                            function updateTips( t ) {
                              tips
                                .text( t )
                                .addClass( "ui-state-highlight" );
                              setTimeout(function() {tips.removeClass( "ui-state-highlight", 1500 );}, 500 );
                            }

                            function addNode() {
                                var nodetext = $("#nodetext").val();
                                var parent = $('#parentlist').val();   
                                var newnode = {"id":0,"text":nodetext,"parent":"#"+parent,"data":{"status":"0"}}; 
                                if(parent == 0) 
                                {
                                    newnode = {"id":0,"text":nodetext,"parent":"#","data":{"status":"0"}};     
                                    parent = "";
                                }
                                createNode("#"+parent, newnode, "last");   
                                send('push_broadcasting_refresh');
                            }

                            dialog = $( "#dialog-form" ).dialog({
                              autoOpen: false,
                              height: 400,
                              width: 350,
                              modal: true,
                              buttons: {
                               "Create new node": addNode,
                                Close: function() {
                                  dialog.dialog( "close" );
                                }
                              },
                              close: function() {
                                form[ 0 ].reset();               
                              }
                            });

                            form = dialog.find( "form" ).on( "submit", function( event ) {
                              event.preventDefault();
                              addNode();                               
                            });

                            $( "a#create").on( "click", function() {
                              InitParentList();
                              dialog.dialog( "open" );
                              InitOverall();
                            });
                      });
		</script>
	</head>
	<body>		
		<div id="jstree1">	
                        <textarea id='log' name='log' readonly='readonly'></textarea><br/>
                        <input type='text' id='message' name='message' />
                        <br><br>
                        <p><font size="5" align="center">Realtime Adjacency Model Using jsTree & WebSocket & PHP by Y.H.SON </font></p>
                        <br><br>
                        <div>Tree is <span id="status">loading</span>.</div>				
			<div>Search for Highlight : <input type="text" id="search"></input></div>	
                        <div><a href="#" id="create">Click here to create new node</a></div>	
                        <div id="jstree"></div>
		</div>	            
              
                <div id="dialog-form" title="Create new node">
                    <p class="validateTips">All form fields are required.</p>
                    <form>
                        <fieldset>
                            <label for="nodetext">Node Caption</label>
                            <input type="text" name="nodetext" id="nodetext" value="" class="text ui-widget-content ui-corner-all">
                            <label for="parentlist">Select a parent</label>
                            <select name="parentlist" id="parentlist" class="text ui-widget-content ui-corner-all"></select>
                            <input type="submit" tabindex="-1" style="position:absolute; top:-1000px">
                        </fieldset>
                    </form>
                </div>
	</body>
</html>
