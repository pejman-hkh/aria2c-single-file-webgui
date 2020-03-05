<?php
ini_set('display_errors', 'Off');

function formatBytes($size, $precision = 6, $b = 'B')
{
    $base = @log($size, 1024);
    $suffixes = array('', 'K'.$b, 'M'.$b, 'G'.$b, 'T'.$b);
    $ret = round(pow(1024, $base - floor($base)), $precision).' '.$suffixes[floor($base)];
    return $ret === 'NAN ' ? '' : $ret;
}

function req( $method, $params = [] ) {
	$ch = curl_init();

	$p = [];
	$p['jsonrpc'] = "2.0";
	$p['id'] = "qwer";
	$p['method'] = "aria2.".$method;
	$p['params'] = $params;

	curl_setopt($ch, CURLOPT_URL, 'http://localhost:6800/jsonrpc');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode( $p ) );
	curl_setopt($ch, CURLOPT_POST, 1);

	$headers = array();
	$headers[] = 'Content-Type: application/json';
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

	$result = curl_exec($ch);
	if (curl_errno($ch)) {
	    echo 'Error:' . curl_error($ch);
	}
	curl_close($ch);

	return json_decode($result, 1);
}

$hpagename = basename($_SERVER['PHP_SELF']);

if( isset( $_GET['stop'] ) ) {
	shell_exec("killall aria2c");
	header("Location: $hpagename");
	exit();	
}

if( isset( $_GET['start'] ) ) {
	$ret = shell_exec("aria2c --enable-rpc=true --daemon=true --log=".__dir__."/test.log --log-level=error");
	sleep(2);
	$res = req('changeGlobalOption', [ [ 'dir' => __dir__.'/files', 'max-connection-per-server' => '16', 'split' => '16', 'min-split-size' => '1M', 'file-allocation' => 'none', 'continue' => 'true', 'auto-file-renaming' => 'false' ] ] );
	header("Location: $hpagename");
	exit();
}

if( isset($_GET['post'] ) )
	$_POST = $_GET;

if( isset( $_GET['new'] ) ) {
    
    $headers = get_headers($_POST['url']);

    $final_url = "";
    foreach ($headers as $h)
    {
        if (preg_match("#location#", $h ))
        {
        $final_url = trim(substr($h,10));
        break;
        }
    }
    
    $pu = parse_url( $_POST['url'] );
   
    if( $final_url )
        $final_url = $pu['scheme'].'://'.$pu['host'].'/'.$final_url;
        
    
	$urls = [ $final_url?:$_POST['url'] ];

	$_POST['opt']['continue'] = 'true';
	
	if( isset( $_POST['dir'] ) ) {
		$_POST['opt']['dir'] = $_POST['dir'];
	}

	if( isset( $_POST['opt']['dir'] ) ) {

		@mkdir($_POST['opt']['dir']);
		$_POST['opt']['dir'] = __dir__.'/'.$_POST['opt']['dir'];
	
	}

	$ret = req('addUri', [ $urls, $_POST['opt'] ] );

	$id = @$ret['result'];
	if( $id ) {
		$ret1 = req('changeOption', [ $id, ['max-connection-per-server' => $_POST['connections'], 'allow-overwrite' => 'true' ] ] );
	}

	echo json_encode( $ret );

	exit();
}

if( isset( $_GET['a'] ) ) {

	$p = [];

	if( isset( $_GET['id']) ){
		$p[] = $_GET['id'];
	}	

	if( isset( $_GET['p']) ){
		$p[] = $_GET['p'];
	}

	$ret = req($_GET['a'], $p );

	echo json_encode($ret);

	exit();	
}

if( isset( $_GET['changeOption'] ) ) {


	$ret = req('changeOption', [ $_GET['changeOption'], $_POST ] );

	echo json_encode($ret);

	exit();
}


if( isset( $_GET['changeGlobalOption'] ) ) {

	$ret = req('changeGlobalOption', [ $_POST ] );

	echo json_encode($ret);

	exit();
}

if( isset( $_GET['pause'] ) ) {
	$ret = req('pause', [ $_GET['pause'] ] );	
	exit();
}

if( isset( $_GET['unpause'] ) ) {
	$ret = req('unpause', [ $_GET['unpause'] ] );	
	exit();
}

if( isset( $_GET['getOption'] ) ) {
	$ret = req('getOption', [ $_GET['getOption'] ] );
	echo json_encode($ret);
	exit();
}

if( isset( $_GET['getGlobalOption'] ) ) {
	$ret = req('getGlobalOption', [] );
	echo json_encode($ret);
	exit();
}

if( isset( $_GET['remove'] ) ) {
	$ret = req('remove', [ $_GET['remove'] ] );
	$ret = req('forceRemove', [ $_GET['remove'] ] );
	echo json_encode($ret);
	exit();
}

function pushArray( &$ref, $array ) {
	foreach( $array as $v ) {
		$ref[] = $v;
	}
}

if( isset( $_GET['refresh'] ) ) {
	echo json_encode( makeList( $_GET['refresh'] ) );
	exit();
}

function makeList( $status = 1 ) {
	$list = [];
	if( $status != 2 ) {
		$list = req('tellActive')['result'];

		pushArray( $list, req('tellWaiting', [ 0,30 ] )['result'] );

		if( $status == 1 ) {
			return $list;
		}
	}

	$fin = req('tellStopped', [ 0,30 ] )['result'];
	foreach( $fin as $v ) {
		$v['fin'] = true;
		$list[] = $v;
	}

	return array_reverse($list);	
}

?>
<!DOCTYPE html>
<html>
<head>
	<title>Aria2c Web GUI</title>
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">

	<link rel="stylesheet" type="text/css" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">

	<style>
	[v-cloak] {
	  display: none;
	}	
	</style>

</head>
<body>
	<div id="app" class="container">
	<h1>Aria2c</h1>

	<a href="?start" class="btn btn-success">Start aria2c daemon</a>
	<a href="?stop" class="btn btn-danger">Stop aria2c daemon</a>
	<a href="?getGlobalOption" class="globalOpts btn btn-primary">Global Configuration</a>
	<br />
	<br />

	<form class="ajaxForm form-inline" method="post" action="?new">
		Url : <input type="text" class="form-control" name="url"> &nbsp;
		Connections : <input type="text" class="form-control" name="connections" value="16" style="width: 100px"> &nbsp;Dir : <input type="text" class="form-control" name="dir" value="files/" style="width: 100px"> &nbsp;
		<input type="submit" class="btn btn-danger" value="Add">
	</form>

	<br />

	<a class="chStatus btn btn-danger btn-sm" href="?status=0">All Status</a>
	<a class="chStatus btn btn-primary btn-sm" href="?status=1">Active</a>
	<a class="chStatus btn btn-success btn-sm" href="?status=2">Finished</a>  
	<br />
	<br />

	<table  class="table">
		<thead>
			<tr>
				<th>Name</th>
				<th>Status</th>
				<th>Size</th>
				<th>Downloaded</th>
				<th>Speed</th>
				<th>Connections</th>
				<th>Opts</th>
			</tr>
		</thead>

		<tbody id="lists">
			
		</tbody>
	</table>

	<div id="OptModalWrapper">
		
	</div>

	<div class="modal fade" id="globaloptsModal" tabindex="-1" role="dialog" aria-labelledby="OptsModalLabel" aria-hidden="true">
	  <div class="modal-dialog" role="document">
	    <div class="modal-content">
	      <div class="modal-header">
	        <h5 class="modal-title" id="exampleModalLabel">Edit Global</h5>
	        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
	          <span aria-hidden="true">&times;</span>
	        </button>
	      </div>
	      <div class="modal-body">
	        <form class="ajaxForm" method="post" action="?changeGlobalOption">

				<div class="form-group">
					<label for="email"> Dir : </label>
					<input type="text" class="form-control" name="dir">
				</div>

				<div class="form-group">
					<label for="email"> Max Connections : </label>
					<input type="text" class="form-control" name="max-connection-per-server">
				</div>

				<div class="form-group">
					<label for="email"> auto-file-renaming : </label>
					<input type="text" class="form-control" name="auto-file-renaming">
				</div>

				<div class="form-group">
					<label for="email"> always-resume : </label>
					<input type="text" class="form-control" name="always-resume">
				</div>

				<div class="form-group">
					<label for="email"> continue : </label>
					<input type="text" class="form-control" name="continue">
				</div>

				<div class="form-group">
					<label for="email"> file-allocation : </label>
					<input type="text" class="form-control" name="file-allocation">
				</div>

				<div class="form-group">
					<label for="email"> max-concurrent-downloads : </label>
					<input type="text" class="form-control" name="max-concurrent-downloads">
				</div>

				<div class="form-group">
					<label for="email"> Split : </label>
					<input type="text" class="form-control" name="split">
				</div>

				<div class="form-group">
					<label for="email"> Min Split Size : </label>
					<input type="text" class="form-control" name="min-split-size">
				</div>

				<div class="form-group">
					<label for="email"> Http Proxy : </label>
					<input type="text" class="form-control" name="http-proxy">
				</div>

				<div class="form-group">
					<label for="email"> Https Proxy : </label>
					<input type="text" class="form-control" name="https-proxy">
				</div>

				<div class="form-group">
					<label for="email"> Ftp Proxy : </label>
					<input type="text" class="form-control" name="ftp-proxy">
				</div>

				<button type="submit" class="btn btn-primary">Save changes</button>
	        </form>
	      </div>
	      <div class="modal-footer">
	        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
	        
	      </div>
	    </div>
	  </div>
	</div>

	<div class="modal fade" id="errorModal" tabindex="-1" role="dialog" aria-labelledby="OptsModalLabel" aria-hidden="true">
	  <div class="modal-dialog" role="document">
	    <div class="modal-content">
	      <div class="modal-header">
	        <h5 class="modal-title" id="exampleModalLabel">Edit Global</h5>
	        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
	          <span aria-hidden="true">&times;</span>
	        </button>
	      </div>
	      <div class="modal-body">

	      </div>
	      <div class="modal-footer">
	        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
	        
	      </div>
	    </div>
	  </div>
	</div>

	</div>

</body>

<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>

<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>

<script>
function basename(path) {
   return path.split('/').reverse()[0];
}

function formatBytes(a,b){if(0==a)return"0 Bytes";var c=1024,d=b||2,e=["Bytes","KB","MB","GB","TB","PB","EB","ZB","YB"],f=Math.floor(Math.log(a)/Math.log(c));return parseFloat((a/Math.pow(c,f)).toFixed(d))+" "+e[f]}

function makeList( lists ) {

	let ret = ``;
	for( let x in lists ) {
		let v = lists[x];
		file = v.files[0];

		ret += `<tr class="`+(v['fin']?'table-success':'')+`">
			<td>`;
		for( let x1 in v.files ) {
			let v1 = v.files[x1];
			ret += `<div>
				<a href="`+(v1.path)+`">`+basename(v1.path)+`</a>
			</div>`;

		}

		ret += `</td>
			<td>`;
			if( v.status == 'paused' ) {
				ret += `<a class="ajax" href="?unpause=`+v.gid+`"><span class="badge badge-danger">`+v.status+`</span></a>`
			} else if( v.status == 'active' ) {
				ret += `<a class="ajax" href="?pause=`+v.gid+`"><span class="badge badge-success">`+v.status+`</span></a>`;
			}
			if( v.fin ) {
				ret += `<a><span class="badge badge-danger">Finished</span></a>`;
			}

			ret += `</td>

			<td>`;

			for( let x1 in v.files ) {
				let v1 = v.files[x1];
				ret += `<div>`+formatBytes(v1.length)+`</div>`;
			}
			ret += `
			</td>
			<td>`;
			for( let x1 in v.files ) {
				let v1 = v.files[x1];
				ret += `<div>
				`+formatBytes(v1.completedLength)+`
				</div>`;
			}
			ret += `
			</td>

			<td>
			`+formatBytes(v.downloadSpeed)+`

			</td>
			<td>
			`+(v.connections)+`
		</td>

			<td>
				<a gid="`+v.gid+`" title="`+basename(file.path)+`" href="?getOption=`+v.gid+`" class="editOpts"><i class="fa fa-cogs text-info"></i></a>


				<a href="?remove=`+v.gid+`" class="ajax"><i class="fa fa-trash text-danger"></i></a>
			</td>
		</tr>
		`;
	}
	
	$("#lists").html(ret);
}

var status = 1;
interval = function() {
	$.ajax({
	  url: "?refresh="+status,
	  dataType : 'JSON',
	}).done(function( data ) {
	  	//app.lists = data;
	  	makeList( data );
	  	setTimeout(function() {
	  		interval();
	  	}, 1000);
	});
};

interval();

$("#app").on("click", ".chStatus", function() {

	status = parseInt($(this).attr('href').match(/status\=([0-9]+)/)[1]);
	return false;
});

function makeOptModal( options ) {
	var ret = `	<div class="modal fade" id="optsModal" tabindex="-1" role="dialog" aria-labelledby="OptsModalLabel" aria-hidden="true">
	  <div class="modal-dialog" role="document">
	    <div class="modal-content">
	      <div class="modal-header">
	        <h5 class="modal-title" id="exampleModalLabel">Edit `+options.name+`</h5>
	        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
	          <span aria-hidden="true">&times;</span>
	        </button>
	      </div>
	      <div class="modal-body">
	        <form class="ajaxForm" method="post" action="?changeOption=`+options.gid+`">
	    
				<div class="form-group">
					<label for="email"> Dir : </label>
					<input type="text" class="form-control" name="dir">
				</div>

				<div class="form-group">
					<label for="email"> Max Connections : </label>
					<input type="text" class="form-control" name="max-connection-per-server">
				</div>

				<div class="form-group">
					<label for="email"> file-allocation : </label>
					<input type="text" class="form-control" name="file-allocation">
				</div>

				<div class="form-group">
					<label for="email"> Split : </label>
					<input type="text" class="form-control" name="split">
				</div>

				<div class="form-group">
					<label for="email"> Min Split Size : </label>
					<input type="text" class="form-control" name="min-split-size">
				</div>

				<div class="form-group">
					<label for="email"> Http Proxy : </label>
					<input type="text" class="form-control" name="http-proxy">
				</div>

				<div class="form-group">
					<label for="email"> Https Proxy : </label>
					<input type="text" class="form-control" name="https-proxy">
				</div>

				<div class="form-group">
					<label for="email"> Ftp Proxy : </label>
					<input type="text" class="form-control" name="ftp-proxy">
				</div>

				<button type="submit" class="btn btn-primary">Save changes</button>
	        </form>
	      </div>
	      <div class="modal-footer">
	        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
	        
	      </div>
	    </div>
	  </div>
	</div>`;

	$("#OptModalWrapper").html(ret);
}

$("#app").on("click", ".editOpts", function() {
	var elm = $(this);
	$.ajax({
	  url: $(this).attr('href'),
	  dataType : 'JSON',
	}).done(function( data ) {
		data.result.name = elm.attr('title');
		data.result.gid = elm.attr('gid');
		let options = data.result;
		//$("#optsModal").replace();
		makeOptModal( options );

	  	$("#optsModal").modal('show');
	  	var form = $("#optsModal");
	  	for( var x in data.result ) {
	  		var val = data.result[x];
	  		form.find("[name='"+x+"']").val( val );
	  	}	  	
	});

	return false;
});

$("#app").on("click", ".globalOpts", function() {
	var elm = $(this);
	$.ajax({
	  url: $(this).attr('href'),
	  dataType : 'JSON',
	}).done(function( data ) {
		//app.globalOptions = data.result;
	  	$("#globaloptsModal").modal('show');
	  	var form = $("#globaloptsModal");
	  	for( var x in data.result ) {
	  		var val = data.result[x];
	  		form.find("[name='"+x+"']").val( val );
	  	}
	});

	return false;
});

$("#app").on("click", ".ajax", function() {

	$.ajax({
	  url: $(this).attr('href'),
	  dataType : 'JSON',
	}).done(function( data ) {
	  	
	});
	return false;
});

$("#app").on("submit", ".ajaxForm" , function() {
	
	$.ajax({
		data : $(this).serialize(),
		url: $(this).attr('action'),
		dataType : 'JSON',
		method : 'POST',
	}).done(function( data ) {
	  	$("#optsModal").modal('hide');
	  	$("#globaloptsModal").modal('hide');

	  	if( data.error ) {
	  		$("#errorModal").modal('show');
	  		$("#errorModal .modal-body").html( '<div class="alert alert-danger">'+data.error.message+'</div>' );
	  	}

	});
	return false;
});
</script>
</html>