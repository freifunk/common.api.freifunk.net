<head>
	<script type="text/javascript" src="//d3js.org/d3.v3.min.js"></script>
	<link rel="stylesheet" href="ext/cal-heatmap/cal-heatmap.css" />
	<script type="text/javascript" src="ext/cal-heatmap/cal-heatmap.min.js"></script>
	<script type="text/javascript" src="http://code.jquery.com/jquery-2.1.4.min.js"></script>
	<script type="text/javascript" src="ext/fullcalendar/lib/moment.min.js"></script>
	<script type="text/javascript" src="ext/fullcalendar/fullcalendar.min.js"></script>
	<script type="text/javascript" src="fullcalendar-data.js"></script>
	<script type="text/javascript" src="http://cdn.jsdelivr.net/qtip2/2.2.1/jquery.qtip.min.js"></script>
	<link rel="stylesheet" href="http://cdnjs.cloudflare.com/ajax/libs/qtip2/2.2.1/jquery.qtip.min.css"></script>
	<script type="text/javascript" src="js/main.js"></script>
	<link rel="stylesheet" href="ext/fullcalendar/fullcalendar.min.css" />
</head>
<body>
	<h1 style="text-align : center">ICS debugging tool </h1>
	<div id="optionPanel">
		[<a id="parseBtn" href="#" class="novisit">Parser</a>] [<a id="mergeBtn" href="#" class="novisit">Merger</a>]
	</div>
	<div id="parserPanel">
		<div id="examplesPanel">Example files 
		<select>
		<?php
			$datafiles = scandir('data');	
			foreach ($datafiles as $file) {
			    if ($file === '.' || $file === '..') {
			        continue;
			    }
			    echo "<option value='$file'>$file</option>";
		}
		?>
		</select>
		</div>
		<textarea id="icstext" placeholder="Copy your ics / iCal text here"></textarea> 
		<button id="importConfirmBtn" onclick="parseFromImported();">Feed me !</button>
	</div>
	<div id="mergePanel" class="hidden">

	</div>
	<hr/>
	<h2>Info</h2>
	<div id="infoPanel">
	</div>
	<h2>Heatmap</h2>
	<div>
		<button id="prevYear">Prev year</button>
		<button id="prev">Prev</button>
		<button id="current">Current</button>
		<button id="next">Next</button>
		<button id="nextYear">Next year</button>
	</div>
	<div id="cal-heatmap"></div>
	<h2>Calendar</h2>
	<div id="full-calendar"></div>
	<br/><br/>
	<a href="http://etud.insa-toulouse.fr/~hadang/fossasia-api/data/" target="_blank">Dataset</a>
	<br/><br/>
	<a href="parser-result.php" target="_blank">Ics parser result</a>
	<style>
	 #cal-heatmap {
	 	margin-top: 20px;
	 }
	 #full-calendar {
	 	margin-top : 20px;
		width: 650px;
  		font-size: 12px;
	 }
	 #examplesPanel {
	 	margin-bottom: 5px;
	 }
	 .hidden {
	 	display: none;
	 }
	 #parserPanel {
	 	margin-top : 20px;
	 }
	 #icstext {
	 	min-width: 500px;
	 	min-height: 100px;
	 }
	 #importConfirmBtn {
	  	vertical-align: bottom;
	 	margin: 4px;
	 	height: 30px;
	 }
	 a.novisit, a.novisit:visited {
	 	color : blue;
	 }
	 table, th, td {
	    border: 1px solid black;
	    border-collapse: collapse;
	}
	th, td {
	    padding: 5px;
	}
	</style>
</body>