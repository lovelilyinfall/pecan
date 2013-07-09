<!DOCTYPE html>
<html>
	<head>
		<title>The workflow failed to execute</title>
		<link rel="stylesheet" type="text/css" href="sites.css" />
		<script type="text/javascript" src="jquery-1.7.2.min.js"></script>
		<script type="text/javascript">
			window.onresize = resize;
			window.onload = resize;
			
        function resize() {
                if ($("#stylized").height() < $(window).height()) {
                        $("#stylized").height($(window).height() - 5);
                }
                $("#output").height($(window).height() - 1);
                $("#output").width($(window).width() - $('#stylized').width() - 5);
        }

			function prevStep() {
				$("#formprev").submit();
			}

			function nextStep() {
				$("#formnext").submit();
			}
		</script>
	</head>
	<body>
		<?php
			$workflowid=$_REQUEST['workflowid'];
			if (isset($_REQUEST['offline'])) {
				$offline=true;
			} else {
				$offline=false;
			}

			require("dbinfo.php");
			$connection=open_database();
			
			// get run information
			$query = "SELECT params, site_id, model_id, model_type, hostname, folder, advanced_edit FROM workflows, models WHERE workflows.id=$workflowid and model_id=models.id";
			$result = mysql_query($query);
			if (!$result) {
				die('Invalid query: ' . mysql_error());
			}
			$workflow = mysql_fetch_assoc($result);
			$pars = $workflow['params'];
			$run_options = eval("return $pars;");	#reassemble the array since it was stored in php code
			$run_pfts = $run_options['pft'];
			//$run_pfts = eval("return $p;");    //For some reason this is not necessary
			
			$folder = $workflow['folder'];
			$model_type = $workflow['model_type'];
			
			// check result
			$status=file($folder . DIRECTORY_SEPARATOR . "STATUS");
			if ($status === FALSE) {
				$status = array();
			}
		?>

		<div id="wrap">
			<div id="stylized">
				<h1>There was an error executing the job.</h1>
				<p>Click "Continue" if you wish to proceed to the results page regardless or "Back" to change parameters and re-run.</p>

				<form id="formprev" method="POST" action="selectdata.php">
					<?php if ($offline) { ?>
						<input name="offline" type="hidden" value="offline">
					<?php } ?>
					<input type="hidden" name="siteid" value="<?=$workflow['site_id']?>" />
					<input type="hidden" name="modelid" value="<?=$workflow['model_id']?>" />
					<input type="hidden" name="modeltype" value="<?=$workflow['model_type']?>" />
					<input type="hidden" name="hostname" value="<?=$workflow['hostname']?>" />
					<input type="hidden" name="psscss" value="<?=$run_options['psscss']?>" />
					<input type="hidden" name="start" value="<?=$run_options['start']?>" />
					<input type="hidden" name="end" value="<?=$run_options['end']?>" />
					<input type="hidden" name="met" value="<?=$run_options['met']?>" />
					<?php foreach ($run_pfts as $pft_iter) { ?>
					<input type="hidden" name="pft[]" value="<?=$pft_iter?>" />
					<?php } ?>
					<input type="hidden" name="advanced_edit" value="<?=$workflow['advanced_edit']?>" /> 
				</form>
				
				<form id="formnext" method="POST" action="finished.php">
					<?php if ($offline) { ?>
						<input name="offline" type="hidden" value="offline">
					<?php } ?>
					<input type="hidden" name="workflowid" value="<?=$workflowid?>" />
					<span id="error" class="small">&nbsp;</span>
					<input id="prev" type="button" value="Back" onclick="prevStep();" />
					<input id="next" type="button" value="Continue" onclick="nextStep();"
				</form>
			</div>
			<div id="output">
				<p>The partial progress of the job is shown below, along with the logfile of the last stage to execute.</p>
				<br>

				<table border=1>
					<tr>
						<th>Stage Name</th>
						<th>Start Time</th>
						<th>End Time</th>
						<th>Status</th>
					</tr>
					<tr>
						<th>setup</th>
						<td><?=startTime("SETUP");?></td>
						<td><?=endTime("SETUP");?></td>
						<td><?=status("SETUP");?></td>
					</tr>
					<tr>
						<th>fia2ed</th>
						<td><?=startTime("FIA2ED");?></td>
						<td><?=endTime("FIA2ED");?></td>
						<td><?=status("FIA2ED");?></td>
					</tr>
					<tr>
						<th>query.trait</th>
						<td><?=startTime("TRAIT");?></td>
						<td><?=endTime("TRAIT");?></td>
						<td><?=status("TRAIT");?></td>
					</tr>
					<tr>
						<th>meta.analysis</th>
						<td><?=startTime("META");?></td>
						<td><?=endTime("META");?></td>
						<td><?=status("META");?></td>
					</tr>
					<tr>
						<th>write.config</th>
						<td><?=startTime("CONFIG");?></td>
						<td><?=endTime("CONFIG");?></td>
						<td><?=status("CONFIG");?></td>
					</tr>
					<tr>
						<th>model</th>
						<td><?=startTime("MODEL");?></td>
						<td><?=endTime("MODEL");?></td>
						<td><?=status("MODEL");?></td>
					</tr>
							<tr>
						<th>output.conversion</th>
						<td><?=startTime("OUTPUT");?></td>
						<td><?=endTime("OUTPUT");?></td>
						<td><?=status("OUTPUT");?></td>
					</tr>
							<tr>
						<th>finished</th>
						<td><?=startTime("FINISHED");?></td>
						<td><?=endTime("FINISHED");?></td>
						<td><?=status("FINISHED");?></td>
					</tr>
				</table>
				<hr/>
			 	<h2>Output from PEcAn</h2>
			 	<textarea id="log" cols="80" rows="10" readonly="readonly">
					<?php
				  	foreach(scandir($folder . DIRECTORY_SEPARATOR) as $file) {
				  		if (preg_match("/^workflow_stage.*\.Rout$/", $file) === 1) {
				  			parselog($folder . DIRECTORY_SEPARATOR . $file);
				  		}
					}
					?>
			 	</textarea>
			</div>
		</div>

		<?php 
		function checkStatus($token) {
		  	global $status;
			foreach ($status as $line) {
				$data = explode("\t", $line);
				if ((count($data) >= 4) && ($data[3] == 'ERROR')) {
					return 2;
				}
			}
			
			if (endTime($token) != "") {
				return 1;
			}
				
			return 0;
		}

		function startTime($token) {
		  global $status;
		  foreach ($status as $line) {
		    $data = explode("\t", $line);
		    if ($data[0] == $token) {
		      return $data[1];
		    }
		  }
		  return "";
		}

		function endTime($token) {
		  global $status;
		  foreach ($status as $line) {
		    $data = explode("\t", $line);
		    if ($data[0] == $token && count($data) >= 3) {
		      return $data[2];
		    }
		  }
		  return "";
		}

		function status($token) {
		  global $folder;
		  global $status;

		  foreach ($status as $line) {
		    $data = explode("\t", $line);
		    if ($data[0] == $token) {
		      if (count($data) >= 4) {
		        return $data[3];
		      }
		      if ($token == "MODEL") {
		        return exec("awk '/Simulating/ { print $3 }' $folder/workflow_stage2.Rout | tail -1");
		      }
		      return "Running";
		    }
		  }
		  return "Waiting";
		}

		function parselog($filename)
		{
			// Open the file
			$f = fopen($filename, "rb");
			if ($f === false) {
				return "file does not exist.";
			}

			// read the file line by line
			while (($buffer = fgets($f, 4096)) !== false) {
				print($buffer);
			}

			// Close file and return
			fclose($f);
		}
		?>

		<script>
			var logtext = document.getElementById('log'); 
			logtext.scrollTop = logtext.scrollHeight;		//This forces the log scroll bar to begin at the bottom for the most recent printout
		</script>
	<body>
<html>
