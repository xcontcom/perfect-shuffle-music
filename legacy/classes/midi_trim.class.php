<?php
require('midi.class.php');

class MidiTrim extends Midi{
	
	//---------------------------------------------------------------
	// trims song to section from $from to $to (or to the end, if $to is omitted)
	//---------------------------------------------------------------
	function trimSong($from=0, $to=false){
	  $tc = count($this->tracks);
	  for ($i=0;$i<$tc;$i++) $this->trimTrack($i, $from, $to);
	}
	
	//---------------------------------------------------------------
	// trims track to section from $from to $to (or to the end, if $to is omitted)
	//---------------------------------------------------------------
	function trimTrack($tn, $from=0, $to=false){
	  $track = $this->tracks[$tn];
	  $new = array();
	  foreach ($track as $msgStr){
	    $msg = explode(' ',$msgStr);
	    $t = (int)$msg[0];
	    if ($t==0)
	    	$new[] = $msgStr;
	    elseif (($t>=$from && ($t<=$to||$to===false))){
	      $msg[0] = $t-$from;
	      $new[] = join(' ',$msg);
	    }
	  }
	  if ($to) $new[] = ($to-$from).' Meta TrkEnd'; // bug-fix!
	  $this->tracks[$tn] = $new;
	}
	
	function timestamp2seconds($ts){
		 return $ts * $midi->getTempo() / $midi->getTimebase() / 1000000;

	}
	function seconds2timestamp($sec){
		return (int)($sec * 1000000 * $this->getTimebase() / $this->getTempo());
	}

}
?>