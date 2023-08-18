<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/VariableProfileHelper.php';

	class Prusalinklocal extends IPSModule
	{
		use VariableProfileHelper;

		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RegisterPropertyString('Hostname', '');
			$this->RegisterPropertyString('Apikey', '');
			$this->RegisterPropertyInteger('UpdateInterval', 60);
			$this->RegisterPropertyString('TimerCondition', '');

			$this->RegisterTimer('PRLL_UpdateData', 0, 'IPS_RequestAction($_IPS[\'TARGET\'], \'UpdateDataTimer\', \'\');');

			$this->CreateVarAndProfiles();
		
		}

		public function Destroy()
		{
			//Never delete this line!
			parent::Destroy();
		}

		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();

			$this->SetTimerInterval('PRLL_UpdateData', $this->ReadPropertyInteger('UpdateInterval') * 1000);

				if ($this->ReadPropertyInteger('UpdateInterval') == 0){
					$this->SetStatus(104);
				} else {
					$this->SetStatus(102);
					$this->UpdateData();
				}
		}
		private function CreateVarAndProfiles()
		{
			require_once __DIR__ . '/../libs/datapoints.php';

			//create Profile
			$this->RegisterProfileFloat('PRLL_Temp', "Temperature", "", " °C", 0, 300, 0.1, 1);

			// create variable
			foreach ($DP as $Datapoint)
			{
				$DP_Identname = $Datapoint['0'];
				$DP_Desc = $Datapoint['1'];
				$DP_Type = $Datapoint['2'];
				$DP_Profile = $Datapoint['3'];
				$DP_Sort = $Datapoint['4'];

				// make symcon happy to create idents
				$DP_Identname = str_replace("-","_",$DP_Identname);
				
				switch ($DP_Type)
				{
					case "BOOL":
						$DP_DataType = 0;
					break;
					case "INT":
						$DP_DataType = 1;
					break;
					case "FLOAT":
						$DP_DataType = 2;
					break;
					case "STRING":
						$DP_DataType = 3;
					break;
				}

				if (!@$this->GetIDForIdent(''.$DP_Identname.''))
				{
					$this->MaintainVariable($DP_Identname, $this->Translate("$DP_Desc"), $DP_DataType, "$DP_Profile", $DP_Sort, true); 
					$this->SendDebug("MaintainVariable:","Create Variable with IDENT ".$DP_Identname, 0);
				}
			}
		}

		public function UpdateData()
		{
			if  (!$this->GetData('printer')){return;} 

			$Printer = $this->GetData('printer');
			$Telemetry = $Printer['telemetry'];
			$Job = $this->GetData('job');
			//create array, but hide error when variable is not reachable
			@$DPValue = [ 
						'temp-bed' => $Telemetry['temp-bed'],
						'temp-nozzle' => $Telemetry['temp-nozzle'],
						'material' => $Telemetry['material'],
						'estimatedPrintTime' => $Job['job']['estimatedPrintTime'],
						'name' => $Job['job']['file']['name'],
						'state' => $Job['state'],
						'completion' => $Job['progress']['completion'],
						'printTime' => $Job['progress']['printTime'],
						'printTimeLeft' => $Job['progress']['printTimeLeft'],
						'printTimeReady' => $Job['progress']['printTimeLeft']
			];

			foreach ($DPValue as $Ident => $Value)
			{
				$Ident = str_replace("-","_",$Ident);

				// Check ident and modify value
				switch($Ident)
				{
					case 'completion':
						$Value = $Value * 100;
					break;
					case 'estimatedPrintTime':
						if ($Value)
						{
							$Time = gmdate('H:i:s',$Value);
							$Value = strtotime('01.01.1970 '.$Time);
						}
					break;
					case 'printTime':
						if ($Value)
						{
							$Time = gmdate('H:i:s',$Value);
							$Value = strtotime('01.01.1970 '.$Time);
						}
					break;
					case 'printTimeLeft':
						if ($Value)
						{
							$Time = gmdate('H:i:s',$Value);
							$Value = strtotime('01.01.1970 '.$Time);
						}
					break;
					case 'printTimeReady':
						// convert time for show expected time..
						if ($Value)
						{
							$Time = date('H:i:s',$Value);
							$TSNow = strtotime(date("M d Y H:i:s"));
							$TSTime = strtotime('01.01.1970 '.$Time);
							$Value = $TSTime+$TSNow;
						}
					break;
				}
				
				if ($Value)
				{				
					$this->SetValue($Ident, $Value);
				}
				else
				{
					$this->SetValue($Ident, 0);
				}
			} 

		}

		private function GetData($Type)
		{
			$ApiKey      = $this->ReadPropertyString('Apikey');
			$IP_Address    = $this->ReadPropertyString('Hostname');
			$uri          = 'http://'.$IP_Address.'/api'."/".$Type;
		
			$postData = [
				'X-Api-Key: '.$ApiKey
			];
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $uri);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
			curl_setopt($ch, CURLOPT_TIMEOUT, 60);
			curl_setopt($ch, CURLOPT_NOSIGNAL, TRUE);		
			$response = curl_exec($ch);
			$curl_error = curl_error($ch);
			curl_close($ch);
			if (empty($response) || $response === false || !empty($curl_error)) {
				$this->SendDebug(__FUNCTION__, 'GetData(): no response from device' . $curl_error, 0);
				$this->LogMessage($this->Translate('GetData(): Error to get data, is the device on?'), KL_ERROR);
				$this->SetStatus(201);
				return false;
			}
			if ($this->GetStatus() == 201) 
				{
					if ( $this->ReadPropertyInteger('UpdateInterval') >0){
						$this->SetStatus(102);
					}
					else
					{
						$this->SetStatus(104);
					}
				} 
			$responseData = json_decode($response, TRUE);
			$this->SendDebug(__FUNCTION__, $response, 0);
			return $responseData;	
		}

		private function UpdateDataTimer()
		{
			if (IPS_IsConditionPassing($this->ReadPropertyString('TimerCondition')))
			{
				if (!$this->GetStatus() == 102)
				{ 
					$this->SetStatus(102);
				}
				$this->UpdateData();
			}
			else
			{
				$this->SetStatus(104);
				$this->SendDebug(__FUNCTION__, "Condition not met", 0);
			}
		}
		// just for use in eventplan or if you want to control the power of the 3d printer with an actor
		public function StartTimer(bool $yes)
		{
			switch($yes)
			{
				case true:
					$this->SetTimerInterval('PRLL_UpdateData', $this->ReadPropertyInteger('UpdateInterval') * 1000);
					$this->SetStatus(102);
					$this->UpdateData();
				break;
				case false:
					$this->SetTimerInterval('PRLL_UpdateData', 0);
					$this->SetStatus(104);
				break;
			}
		}

		public function RequestAction($Ident, $Value)
		{
			switch($Ident)
			{
				case "UpdateDataTimer":
					$this->UpdateDataTimer();
				break;
			}
		}
	}