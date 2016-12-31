<?php
/**
 * @brief Restore Player's Stamina [요청한 플레이어의 스테미너를 회복함]
 * @author Jiyeol Pyo
 * @date 2013/06/17
 */

include_once "../include/header.php";
include_once "../database/dbmanager.php";
include_once "../character/charactermanager.php";
include_once "../log/logmanager.php";

$AccountUniqueID = ConvertToString($_REQUEST['AccountID']);
$CharacterID = ConvertToString($_REQUEST['CharacterID']);
$ProcedureParameters = array($AccountUniqueID, $CharacterID);

if( false == ValidateEmpty( $ProcedureParameters ) )
{
	$ServerResult = array('Type'=>EPacketType::RestoreStaminaByRUA, 'ServerResult'=>EServerResult::ValidateFailedParameters);
	$ServerResponseData = array('ResponseData'=>array(NULL));
	MakeServerResult($ServerResult, $ServerResponseData);
	exit;
}

$RequireRestoreRua = 1;
$CurrentRua = $AccountManager->GetPlayerRua( $AccountUniqueID );
if( 0 == $CurrentRua )
{
	$ServerResult = array('Type'=>EPacketType::RestoreStaminaByRUA, 'ServerResult'=>EServerResult::NotEnoughValue);
	$ServerResponseData = array('ResponseData'=>array(NULL));
	MakeServerResult($ServerResult, $ServerResponseData);
	exit;
}
else
{
	if( $RequireRestoreRua > $CurrentRua )
	{
		$ServerResult = array('Type'=>EPacketType::RestoreStaminaByRUA, 'ServerResult'=>EServerResult::NotEnoughValue);
		$ServerResponseData = array('ResponseData'=>array(NULL));
		MakeServerResult($ServerResult, $ServerResponseData);
		exit;
	}
	else if( $RequireRestoreRua <= $CurrentRua )
	{
		$CalcRua = CalcPlayerValues( EPlayerValues::Rua, ECalcOperation::Minus, $CurrentRua, $RequireRestoreRua );

		// 차감 된 플레이어의 루아 업데이트.
		$UpdateRua = $AccountManager->UpdateRua( $AccountUniqueID, $CalcRua );
		$DBResult = $UpdateRua["@iResult"];
		if( EDBResult::Success == $DBResult || EDBResult::FinalSuccess == $DBResult )
		{
			// 루아 업데이트 성공!
			$CharLevel = $CharacterManager->GetLevelByCharacterID( $CharacterID );
			$CalcNewStamina = $AccountManager->CalcStamina( $CharLevel );
			$AccountManager->UpdateStamina( $AccountUniqueID, $CalcNewStamina );

			$ResponseData = array(ConvertToString($AccountUniqueID), ConvertToString($CharacterID), ConvertToString($CalcRua), ConvertToString($CalcNewStamina));

			$ServerResult = array('Type'=>EPacketType::RestoreStaminaByRUA, 'ServerResult'=>DBResultToServerResult($DBResult));
			$ServerResponseData = array('ResponseData'=>$ResponseData);
			MakeServerResult($ServerResult, $ServerResponseData);
		}
		else
		{
			$ResponseData = array(NULL);
			$ServerResult = array('Type'=>EPacketType::RestoreStaminaByRUA, 'ServerResult'=>EServerResult::DBFailed);
			$ServerResponseData = array('ResponseData'=>$ResponseData);
			MakeServerResult($ServerResult, $ServerResponseData);
		}

		$LogManager->WriteLibraryLog($AccountUniqueID, $CharacterID, ELogLevel::Info, EPacketType::ContinueBattle, "Continue Battle!");
	}	
}
?>