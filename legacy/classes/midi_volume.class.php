<?php
require('midi.class.php');

class MidiVolume extends Midi{
	
	//---------------------------------------------------------------
	// sets global volume (0..127) by adding new volume controllers for channel 1-16
	// (and removing existing volume controllers)
	//---------------------------------------------------------------
	function setGlobalVolume($vol, $removeAll=true){
		// find right position in first track: after all other events with time=0, 
		// but before the first note-on event
		
		// test: remove ALL volume controllers in ALL tracks
		if ($removeAll){
			$trackCnt = count($this->tracks);
			for ($i=0;$i<$trackCnt;$i++){
				$msgCnt = count($this->tracks[$i]);
				for ($j=$msgCnt-1;$j>=0;$j--){
					$msg = explode(" ", $this->tracks[$i][$j]);
					if ($msg[1]=='Par'&&$msg[3]=='c=7')
						array_splice($this->tracks[$i], $j, 1);
					#	array_splice($this->tracks[$tn], $mn, 1);
				}
			}
		}
		
		$i=0;
		$cnt = count($this->tracks[0]);
		while ($i<$cnt){
			$msg = explode(" ", $this->tracks[0][$i]);
			if ($msg[0]!=0 || $msg[1]=='On' || $msg[2]=='TrkEnd') break;
			// remove all existing volume controllers
			if ($msg[1]=='Par'&&$msg[3]=='c=7')
				array_splice($this->tracks[0], $i, 1);
			else $i++;
		}
		// add 16 new volume controller messages
		$msgList = array();
		for ($ch=1;$ch<17;$ch++)
			$msgList[] = "0 Par ch=$ch c=7 v=$vol";
		// insert them at found position
		array_splice($this->tracks[0], $i, 0, $msgList);
				
	}
	
	function setChannelVolume($chan, $vol){
		$i=0;
		$cnt = count($this->tracks[0]);
		while ($i<$cnt){
			$msg = explode(" ", $this->tracks[0][$i]);
			if ($msg[0]!=0 || $msg[1]=='On') break;
			// remove existing volume controller for specified channel
			if ($msg[1]=='Par' && $msg[2]=="ch=$chan" && $msg[3]=='c=7')
				array_splice($this->tracks[0], $i, 1);
			else $i++;
		}
		// add new volume controller messages for specified channel
		$msg = "0 Par ch=$chan c=7 v=$vol";
		array_splice($this->tracks[0], $i, 0, $msg);
	}
	
	// returns array (channel=>volume) of all found volume controllers 
	function getVolumes(){
		// look for volume controllers with time=0 in first track
		$volumes = array();
		$i=0;
		$cnt = count($this->tracks[0]);
		while ($i<$cnt){
			$msg = explode(" ", $this->tracks[0][$i]);
			if ($msg[0]!=0) break;
			//"0 Par ch=$ch c=7 v=$vol"
			if ($msg[1]=='Par'&&$msg[3]=='c=7'){
				eval("\$".$msg[2].';'); // ch
				eval("\$".$msg[4].';'); // v
				$volumes[$ch] = $v;
			}
			$i++;
		}
		return $volumes;
	}
}


// TEST:

//$file = '../sample_files/Mortal Kombat.mid';
//$midi = new MidiVolume();
//$midi->importMid($file);
//print_r($midi->getVolumes());
// $midi->downloadMidFile('converted.mid');


//$midi->setVolume(30);
//



//$save_dir = '../tmp/';
//srand((double)microtime()*1000000);
//$file = $save_dir.rand().'.mid';
//$midi->saveMidFile($file);
//$midi->playMidFile($file,1,1,0,'wm');
//
//echo '<hr><pre>';
//echo $midi->getTxt();
//echo '</pre>';

?>