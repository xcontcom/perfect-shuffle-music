<?php
//0 PrCh ch=1 p=24\n";
$notescount=256;
require_once('./classes/midi.class.php');

function midigenerete($file1, $n, $notescount){
	//$minorarray=array(40, 41, 43, 45, 47, 48, 50, 52, 53, 55, 57, 59, 60, 62, 64, 65, 67, 69, 71, 72, 74, 76);
	$minorarray=array(48, 50, 52, 53, 55, 57, 59, 60, 62, 64, 65, 67, 69, 71, 72, 74);
	$txt = "MFile 0 1 48\nMTrk
	0 Meta Text \"Genetic algorithm\"
	0 PrCh ch=1 p=24\n";
	$tmptxt="";
	$on=0;
	for($i=0; $i<$notescount; $i++){
		$off=$on+12;
		$nn=$minorarray[$n[$i]];
		$tmptxt.=$on." On ch=1 n=".$nn." v=100\n";
		$tmptxt.=$off." Off ch=1 n=".$nn." v=100\n";
		$on=$off;
	}
	$txt.=$tmptxt.$on." Meta TrkEnd\nTrkEnd"; 
	$midi = new Midi();
	$midi->importTxt($txt);
	$midi->saveMidFile($file1, 0666);
}

function checkbox($s){
	echo "<form method=\"post\">";
	for($i=0;$i<$s;$i++){
		echo "<a href=\"test".$i.".mid\" >".$i."</a>";
		echo "<input type=\"checkbox\" name=\"option[".$i."]\" value=\"1\">".$i."<br />";
	}
	echo "<input type=\"submit\" name=\"submit\">";
	echo "</form>";
}

function filepop($notescount, $population=false){
	$filename="population.txt";
	if($population==false){
		if(!file_exists($filename)){
			for($i=0;$i<16;$i++){
				$startsound=$i;
				for($j=0;$j<$notescount;$j++){
					$population[$i][$j]=$startsound;//rand(0,21);
					$population[$i+16][$j]=$startsound;
					$population[$i+32][$j]=$startsound;
				}
			}
			$buffer=serialize($population);
			$handle=fopen($filename, "w");
			fwrite($handle, $buffer);
			fclose($handle);
		}else{
			$handle=fopen($filename, "r");
			$buffer=fread($handle, filesize($filename));
			$population=unserialize($buffer);
			fclose($handle);
		}
	}else{
		$buffer=serialize($population);
		$handle=fopen($filename, "w");
		fwrite($handle, $buffer);
		fclose($handle);
	}
	return $population;
}

$population=filepop($notescount);

for($i=0;$i<16;$i++){
	$startsound=$i;
	for($j=0;$j<$notescount;$j++){
		$startarray[$i][$j]=$startsound;
		$startarray[$i]['fitness']="temp";
	}
}

if(isset($_POST['submit'])){
	for($i=0;$i<48;$i++){
		$population[$i]['fitness']=(int)$_POST['option'][$i];
	}
	$by = 'fitness';
	usort($population, function($first, $second) use($by){
	if ($first[$by]>$second[$by]) {return -1;}
	elseif ($first[$by]<$second[$by]) {return 1;}
	return 0;
	});
	for($i=0;$i<16;$i++){
		$newpopulation[$i]=$population[$i];
		unset($newpopulation[$i]['fitness']);
	}
	for($i=0;$i<16;$i++){
		$newpopulation[$i+16]=$startarray[$i];
	}
	shuffle($newpopulation);
	$k=0;
	for($i=0;$i<16;$i++){
		$i2=$i+16;
		$k2=$k+1;
		for($j=0;$j<$notescount;$j++){
			$tarray[$j*2]=$newpopulation[$i][$j];
			$tarray[$j*2+1]=$newpopulation[$i2][$j];
		}
		for($j=0;$j<$notescount;$j++){
			$newpop[$k][$j]=$tarray[$j];
			$newpop[$k2][$j]=$tarray[$j+8];
		}
		$k+=2;
		if(!isset($newpopulation[$i]['fitness'])){
			$newpop[$k]=$newpopulation[$i];
			$k++;
		}
		if(!isset($newpopulation[$i2]['fitness'])){
			$newpop[$k]=$newpopulation[$i2];
			$k++;
		}
	}
	
	$population=$newpop;
	filepop($notescount, $population);
}


//echo "<pre>";
//print_r($population);
//echo "</pre>";
//echo "<br />";

for($i=0;$i<48;$i++){
	echo "<br />";
	for($j=0;$j<32;$j++){
		echo $population[$i][$j].", ";
	}
}

for($i=0;$i<48;$i++){
	$n=$population[$i];
	$file1="midi/test".$i.".mid";
	midigenerete($file1, $n, $notescount);
}

checkbox(48);

?>