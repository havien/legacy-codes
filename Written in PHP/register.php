<?php
/**
 * @brief Register User Account [신규 계정 가입]
 * @author Jiyeol Pyo
 * @date 2013/06/12
 */
include_once "../include/header.php";
include_once "../include/define.php";
include_once "../database/dbmanager.php";
include_once "../log/logmanager.php";
include_once "../include/csvmanager.php";
include_once "../card/cardmanager.php";
include_once "../friend/friendmanager.php";

$KakaoID = ConvertToString($_REQUEST['KakaoID']);
$NickName = ConvertToString($_REQUEST['NickName']);
$ClientOS = ConvertToInt($_REQUEST['ClientOS']);

// Non-Select.
$ServerResult = NULL;
$AccountUniqueID = "@iAccountUniqueID";
$CharacterUniqueID = "@iCharacterUniqueID";
$ProcedureParameters = array( $KakaoID, $NickName, $AccountUniqueID, $CharacterUniqueID );
if( false == ValidateEmpty( $ProcedureParameters ) )
{
	$ServerResult = array('Type'=>EPacketType::Register, 'ServerResult'=>EServerResult::ValidateFailedParameters);
	$ServerResponseData = array('ResponseData'=>array(NULL));
	MakeServerResult($ServerResult, $ServerResponseData);
	exit;
}

$NewAccountID = 0;
$Result = $DBManager->ExecuteNonSelectProcedure("SP_INSERT_ACCOUNT", $ProcedureParameters);
if( false == $Result )
{
	$ResponseData = NULL;
	$ServerResult = EServerResult::DBError;
}
else
{
	$ResponseData = array_values($Result);
	$DBResult = $Result["@iResult"];

	$NewAccountID = $Result[$AccountUniqueID];
	$NewCharacterID = $Result[$CharacterUniqueID];

	$ServerResult = DBResultToServerResult($DBResult);
}

$ServerResult = array('Type'=>EPacketType::Register, 'ServerResult'=>$ServerResult);
$ServerResponseData = array('ResponseData'=>$ResponseData);
MakeServerResult($ServerResult, $ServerResponseData);

// 랜덤으로 친구 20명을 추가 해 놓음.
// $DBResultRows = $DBManager->ExecuteSelectProcedure( "SP_SELECT_RANDOM_ACCOUNT_ID", array($NewAccountID) );
// for( $Counter = 0; $Counter < count( $DBResultRows ); ++$Counter )
// {
// 	$FriendManager->AddFriend( $NewAccountID, $DBResultRows[$Counter][0] );
// }

$CharacterID = $NewAccountID;
$LogManager->WriteLibraryLog($NewAccountID, $CharacterID, ELogLevel::Info, EPacketType::Register, "Register Complete!");
?>