<?php
define('__ROOT__', dirname(dirname(__FILE__)));
require_once(__ROOT__ . '/libs/autoload.php');

class IPSMusicCastDevice extends IPSModule
{
    public function Create()
    {
        parent::Create();
		$this->RegisterPropertyString('DeviceID', ''); //Device ID
		$this->RegisterPropertyString('Host', ''); //Device IP
		$this->RegisterPropertyString('Name', ''); //Device Name

		$this->RegisterVariableBoolean("Power", "Power");
		IPS_SetVariableCustomProfile($this->GetIDForIdent("Power"), "~Switch");
		IPS_SetPosition($this->GetIDForIdent("Power"), 0);
		$this->EnableAction("Power");
		
		$this->RegisterVariableInteger("State", "State");
		IPS_SetVariableCustomProfile($this->GetIDForIdent("State"), "MUC_State");
		IPS_SetPosition($this->GetIDForIdent("State"), 1);
		$this->EnableAction("State");
		
		$this->RegisterVariableInteger("PreviousNext", "PreviousNext");
		IPS_SetVariableCustomProfile($this->GetIDForIdent("PreviousNext"), "MUC_PreviousNext");
		IPS_SetPosition($this->GetIDForIdent("PreviousNext"), 2);
		$this->EnableAction("PreviousNext");
		
		$this->RegisterVariableInteger("Volume", "Volume");
		IPS_SetPosition($this->GetIDForIdent("Volume"), 4);
		IPS_SetVariableCustomProfile($this->GetIDForIdent("Volume"), "MUC_Volume");
		$this->EnableAction("Volume");
		
		$this->RegisterVariableBoolean("Mute", "Mute");
		IPS_SetVariableCustomProfile($this->GetIDForIdent("Mute"), "MUC_Mute");
		IPS_SetPosition($this->GetIDForIdent("Mute"), 5);
		$this->EnableAction("Mute");

		$this->RegisterVariableInteger("Input", "Input");
		IPS_SetPosition($this->GetIDForIdent("Input"), 6);
		$this->EnableAction("Input");
		
		$this->RegisterVariableString("Playtime", "Play Time");
		IPS_SetPosition($this->GetIDForIdent("Playtime"), 7);
		
		$this->RegisterVariableString("Title", "Title");
		IPS_SetPosition($this->GetIDForIdent("Title"), 8);
		
		$this->RegisterVariableString("Artist", "Artist");
		IPS_SetPosition($this->GetIDForIdent("Artist"), 9);
		
		$this->RegisterVariableString("Album", "Album");
		IPS_SetPosition($this->GetIDForIdent("Album"), 10);
		
		$this->RegisterVariableString("AlbumArt", "Cover");
		IPS_SetPosition($this->GetIDForIdent("AlbumArt"), 11);
		IPS_SetVariableCustomProfile($this->GetIDForIdent("AlbumArt"), "~HTMLBox");

		// register update timer for Device subscription
		$this->RegisterTimer('subscribeDevicesTimer', 2000, 'MUC_subscribeDevice($_IPS[\'TARGET\']);');

		// register onetime-timer for Device Setup
		$this->RegisterTimer('setupOneTimeTimer', 2000, 'MUC_DeviceSetup($_IPS[\'TARGET\']);');
    }

    public function Destroy()
    {
        parent::Destroy();
		//Funktioniert nicht, warum?
		
		/*if (IPS_VariableProfileExists("MUC_Input_" . $this->ReadPropertyString('DeviceID')))
		{
			IPS_DeleteVariableProfile("MUC_Input_" . $this->ReadPropertyString('DeviceID'));
		}*/
    }
//Modul gespeichert
	    public function ApplyChanges()
    {
        parent::ApplyChanges();
		$this->ConnectParent("{82347F20-F541-41E1-AC5B-A636FD3AE2D8}");
    }

public function subscribeDevice()
	{
		IPS_LogMessage("MUC ". $this->ReadPropertyString('Name'), "Subscribe Device: " . $this->ReadPropertyString('Host'));
		$DeviceHost = $this->ReadPropertyString('Host');
		$musicCastClient = new MusicCast\Client(['host' => $DeviceHost,'port' => 80,]);
		$result = $musicCastClient->api('events')->subscribe();
		$timer = 540000; //9 Minuten
		$this->SetTimerInterval('subscribeDevicesTimer', $timer);
	}

public function updateSpeakerIP()
{
		//Update device IP
		$tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "musiccast";
		$this->delete_files($tempPath);
		$CurrentIP = $this->getSpeakerIPbyName($this->ReadPropertyString('Name'));
		if($CurrentIP != $this->ReadPropertyString('Host'))
			{
				IPS_LogMessage("MUC " . $this->ReadPropertyString('Name'), "Device IP updated");
				SetValue($this->GetIDForIdent('Input'),$CurrentIP);
			} else {
				IPS_LogMessage("MUC " . $this->ReadPropertyString('Name'), "Device IP is still valid");
			}
		$this->SetStatus(102);
}
	//Get Data from UDP Socket
		public function ReceiveData($JSONString)
		{
			$data = json_decode($JSONString);
			//Parse and write values to our buffer
			$this->SetBuffer("MusicCastDeviceBuffer", utf8_decode($data->Buffer));
			$MUC_Buffer = $this->GetBuffer("MusicCastDeviceBuffer");
			$MUC_Buffer_JSON = json_decode($MUC_Buffer,false);
			//print_r($MUC_Buffer_JSON);
			if ($MUC_Buffer_JSON->device_id == $this->ReadPropertyString('DeviceID'))
			{
				if (key($MUC_Buffer_JSON) == 'main') {
					if (property_exists($MUC_Buffer_JSON->main, 'mute')) {
							IPS_LogMessage("MUC " . $this->ReadPropertyString('Name') . " [Main]", $MUC_Buffer_JSON->main->mute);
							$this->SetValue("Mute", $MUC_Buffer_JSON->main->mute);
						}
					if (property_exists($MUC_Buffer_JSON->main, 'volume')) {
							IPS_LogMessage("MUC " . $this->ReadPropertyString('Name') . " [Main] Volume", $MUC_Buffer_JSON->main->volume);
							$this->SetValue("Volume", $MUC_Buffer_JSON->main->volume);
						}
					if (property_exists($MUC_Buffer_JSON->main, 'input')) {
							IPS_LogMessage("MUC " . $this->ReadPropertyString('Name') . " [Main] Input", $MUC_Buffer_JSON->main->input);
							SetValue($this->GetIDForIdent('Input'), $this->getVariableIntegerbyName($this->GetIDForIdent('Input'),$MUC_Buffer_JSON->main->input));
						}
					if (property_exists($MUC_Buffer_JSON->main, 'signal_info_updated')) {
							IPS_LogMessage("MUC " . $this->ReadPropertyString('Name') . " [Main]->signal_info_updated", $MUC_Buffer_JSON->main->signal_info_updated);
							$this->updateSpeakerInfos();
						}	
					if (property_exists($MUC_Buffer_JSON->main, 'power')) {
							IPS_LogMessage("MUC " . $this->ReadPropertyString('Name') . " [Main]->power", $MUC_Buffer_JSON->main->power);
							if ($MUC_Buffer_JSON->main->power == 'on')
							{
								SetValue($this->GetIDForIdent('Power'),true);
								$this->setHiddenDeviceVariable($this->GetIDForIdent('Power'),false);
							}else
							{
								SetValue($this->GetIDForIdent('Power'),false);
								$this->setHiddenDeviceVariable($this->GetIDForIdent('Power'),true);
							}
						}
				}
				if (key($MUC_Buffer_JSON) == 'netusb')
				{
					
					if (property_exists($MUC_Buffer_JSON->netusb, 'play_time')) {
						$playtime = gmdate("H:i:s", $MUC_Buffer_JSON->netusb->play_time);
						$this->SetValue("Playtime", $playtime);
					}
					if (property_exists($MUC_Buffer_JSON->netusb, 'signal_info_updated')) {
						IPS_LogMessage("MUC " . $this->ReadPropertyString('Name') . " [netusb]->signal_info_updated", $MUC_Buffer_JSON->netusb->signal_info_updated);
					}
					if (property_exists($MUC_Buffer_JSON->netusb, 'play_info_updated')) {
						IPS_LogMessage("MUC " . $this->ReadPropertyString('Name') . " [netusb]->player_info_updated", $MUC_Buffer_JSON->netusb->play_info_updated);
						$this->updateSpeakerInfos();
					}
				}
			}
		}
		

public function updateSpeakerInfos()
	{
		$musicCastNetwork = new MusicCast\Network;
		$DeviceHost = $this->ReadPropertyString('Host');
		$musicCastDevice = new MusicCast\Device($DeviceHost);
		$musicCastSpeaker = new MusicCast\Speaker($musicCastDevice);
		$musicCastController = new MusicCast\Controller($musicCastSpeaker,$musicCastNetwork,1);
		//Update Album Details
		$StateDetails = $musicCastController->getStateDetails();
		$this->SetValue("Title", $this->getObjProp($StateDetails->track,"title"));
		$this->SetValue("Artist", $this->getObjProp($StateDetails->track,"artist"));
		$this->SetValue("Album", $this->getObjProp($StateDetails->track,"album"));
		if ($this->getObjProp($StateDetails->track,"albumArt") == "")
		{
			IPS_SetHidden($this->GetIDForIdent('AlbumArt'),true);
		}else{
			$AlbumArtHTML = "<img src='" . $this->getObjProp($StateDetails->track,"albumArt") . "' style='height: 20%; width: 20%; object-fit: fill;' />";
			$this->SetValue("AlbumArt", $AlbumArtHTML);
			IPS_SetHidden($this->GetIDForIdent('AlbumArt'),false);
		}
		//Update State (play,stop,pause)
		$this->SetValue("State", $musicCastController->getState());
		//Update Power 
		$powerstate = $musicCastController->isPowerOn();
			if($powerstate == false)
			{
				$this->SetValue("Power", $powerstate);
				$this->setHiddenDeviceVariable($this->GetIDForIdent("Power"),true);
				//$this->SetTimerInterval('subscribeDevicesTimer', 0);
			}else{
				$this->SetValue("Power", $powerstate);
				$this->setHiddenDeviceVariable($this->GetIDForIdent("Power"),false);
			}
		
		//Update Volume 
		$this->SetValue("Volume", $musicCastController->getVolume());
		//Update Mute 
		$this->SetValue("Mute", $musicCastController->isMuted());
		//Update Input
		$CurrentInput = $musicCastController->getInput();
		$this->SetValue("Input", $this->getVariableIntegerbyName($this->GetIDForIdent('Input'),$CurrentInput));
	}

//function extract protected properties
protected function getObjProp($obj, $val){
	$propGetter = Closure::bind( function($prop){return $this->$prop;}, $obj, $obj );
	return $propGetter($val);
}

public function RequestAction($Ident, $Value) {
 		$musicCastNetwork = new MusicCast\Network;
		$DeviceHost = $this->ReadPropertyString('Host');
		$musicCastDevice = new MusicCast\Device($DeviceHost);
		$musicCastSpeaker = new MusicCast\Speaker($musicCastDevice);
		$musicCastController = new MusicCast\Controller($musicCastSpeaker,$musicCastNetwork,1);


    switch($Ident) {
        case "Mute":
			if($Value == true)
			{
				$result = $musicCastController->mute();
			}else{
				$result = $musicCastController->unmute();
			}
            break;
        case "Power":
			if($Value == false)
			{
				$result = $musicCastController->standBy();
				$this->setHiddenDeviceVariable($this->GetIDForIdent($Ident),true);
			}else{
				$result = $musicCastController->powerOn();
				$this->setHiddenDeviceVariable($this->GetIDForIdent($Ident),false);
				$this->subscribeDevice();
				$this->updateSpeakerInfos();
			}
            break;
        case "Volume":
			$result = $musicCastController->setVolume($Value);
            break;
        case "State":
			$result = $musicCastController->setState($Value);
            break;
        case "Input":
			$InputName = $this->getVariableValueName($this->GetIDForIdent($Ident),$Value);
			$result = $musicCastController->setInput($InputName);
            break;
        case "PreviousNext":
			if($Value == false)
			{$result = $musicCastController->previous();}
			else
			{$result = $musicCastController->next();}
			
            break;
        default:
            throw new Exception("Invalid Ident");
    }
}
//Versteckt alle Objekte wenn Power vom Device off ist
protected function setHiddenDeviceVariable($PowerChildrenID,$hide)
{
	$ParentID = IPS_GetParent($PowerChildrenID);
	$ChildrenIDs = IPS_GetChildrenIDs($ParentID);
	foreach ($ChildrenIDs as $ChildrenID)
	{
		if(IPS_GetName($ChildrenID) != "Power")
		{
		IPS_SetHidden($ChildrenID,$hide);
		}
	}
}

//Wird ausgefürt nachdem die Instanze angelegt wurde (durch Timer)
public function DeviceSetup()
{
	$this->SetTimerInterval('setupOneTimeTimer', 0);
	$this->createInputVariablenprofile();
	IPS_SetVariableCustomProfile($this->GetIDForIdent("Input"), "MUC_Input_" . $this->ReadPropertyString('DeviceID'));
	$this->updateSpeakerIP();
	$this->updateSpeakerInfos();
}
protected function getVariableValueName($VariableID,$VariableValue)
{
	$VariableObject = IPS_GetVariable($VariableID);
	$VariableCustomProfileName = $VariableObject['VariableCustomProfile'];
	$VariableProfileObject = IPS_GetVariableProfile($VariableCustomProfileName);
	$VariableProfileValueName = $VariableProfileObject['Associations'][$VariableValue]['Name'];
	return $VariableProfileValueName;
}

protected function getVariableIntegerbyName($VariableID,$VariableValueName)
{
	$i=0;
	$VariableObject = IPS_GetVariable($VariableID);
	$VariableCustomProfileName = $VariableObject['VariableCustomProfile'];
	$VariableProfileObject = IPS_GetVariableProfile($VariableCustomProfileName);
	foreach($VariableProfileObject['Associations'] as $VariableProfileObjectSub)
	{
		if ($VariableProfileObjectSub['Name'] == $VariableValueName)
		{
			$VariableProfileValueInt = $i;
		}
		$i++;
	}
	
	return $VariableProfileValueInt;
}

protected function getSpeakerIPbyName($SpeakerName)
{
		$musicCastNetwork = new MusicCast\Network;
		try {
				$speaker = $musicCastNetwork->getSpeakerByName($SpeakerName);
				$DeviceObj = $this->getObjProp($speaker,"device");
				$DeviceIP = $this->getObjProp($DeviceObj,"ip");
				return $DeviceIP;
			}
		catch (Exception $e) {
				$this->SetStatus(104);
				echo 'Error: ',  $e->getMessage(), "\n";
				exit(1);
		}
}


/*
public function getNetPreset(){
			$Data = "netusb/getPresetInfo"; 
			$Answer = $this->SendCommand($Data);
			$NetPresets = json_decode($Answer,false);
			return ($NetPresets); 
			}
			*/
			
			
//Input Variable Profile create
protected function createInputVariablenprofile()
	{
		$DeviceID = $this->ReadPropertyString('DeviceID');
		$musicCastNetwork = new MusicCast\Network;
		$DeviceHost = $this->ReadPropertyString('Host');
		$musicCastDevice = new MusicCast\Device($DeviceHost);
		$musicCastSpeaker = new MusicCast\Speaker($musicCastDevice);
		$musicCastController = new MusicCast\Controller($musicCastSpeaker,$musicCastNetwork,1);
		$Inputs = $musicCastController->getInputList();
		
		$VariablenProfileName = "MUC_Input_" . $DeviceID;
		if (!IPS_VariableProfileExists($VariablenProfileName)) 
		{
			IPS_CreateVariableProfile($VariablenProfileName, 1);
			$i = 0;
			foreach ($Inputs as $Input){
				IPS_SetVariableProfileAssociation($VariablenProfileName, $i, $Input, "Speaker",-1);
				$i++;
			}
		}
	}
	
	protected function getInstanceNameExist($InstanceName)
		{
			$Instanceexist = false;
			$Instances = IPS_GetInstanceList();
			foreach($Instances as $Instance)
			{
				$InstanceObject = IPS_GetInstance($Instance);
				$InstanceObjectName = IPS_GetName($InstanceObject['InstanceID']);
				
				if ($InstanceObjectName == $InstanceName) $Instanceexist = true;
			}
			return $Instanceexist;
		}
    /**
     * reconnect parent socket
     * @param bool $force
     */
    public function ReconnectParentSocket($force = false)
    {
        $ParentID = $this->GetParentId();
        if (($this->HasActiveParent() || $force) && $ParentID > 0) {
            IPS_SetProperty($ParentID, 'Open', true);
            @IPS_ApplyChanges($ParentID);
        }
    }

function delete_files($target) {
    if(is_dir($target)){
        $files = glob( $target . '*', GLOB_MARK ); //GLOB_MARK adds a slash to directories returned

        foreach( $files as $file ){
            delete_files( $file );      
        }

        rmdir( $target );
    } elseif(is_file($target)) {
        unlink( $target );  
    }
}
}