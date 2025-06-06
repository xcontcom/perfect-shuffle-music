<?php
require('midi.class.php');

class MidiConversion extends Midi{
	
	//---------------------------------------------------------------
	// converts midi file of type 1 (multiple tracks) to type 0 (single track)
	//---------------------------------------------------------------
	function convertToType0(){
		$this->type = 0;
		if (count($this->tracks)<2) return;
		$singleTrack = array();
		foreach ($this->tracks as $track) {
			array_pop ($track); // remove Meta TrkEnd
			$singleTrack = array_merge($singleTrack, $track);
		}
		usort ($singleTrack, create_function('$a,$b','$ta =(int)strtok($a,\' \');$tb=(int)strtok($b,\' \');return $ta==$tb?0:($ta<$tb?-1:1);'));
		$endTime = strtok($singleTrack[count($singleTrack)-1], " ");
		$singleTrack[] = "$endTime Meta TrkEnd";
		$this->tracks = array($singleTrack);
	}
	
}

// TEST:
// $midi = new MidiConversion();
// $midi->importMid($file);
// $midi->convertToType0();
// $midi->downloadMidFile('converted.mid');
// exit();

?>