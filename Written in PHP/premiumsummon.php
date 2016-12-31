<?php
/**
 * @brief Summon New Card, Type of Premium [루아를 소모하여 프리미엄 카드 소환]
 * @author Jiyeol Pyo
 * @date 2013/06/21
 */

include_once "../include/header.php";
include_once "../include/define.php";
include_once "../database/dbmanager.php";
include_once "../log/logmanager.php";
include_once "../include/csvmanager.php";
include_once "./cardmanager.php";
include_once "../character/charactermanager.php";

$AccountUniqueID = ConvertToString($_REQUEST['AccountID']);
$CharacterID = ConvertToString($_REQUEST['CharacterID']);
$Parameters = array( $AccountUniqueID );
if( false == ValidateEmpty( $Parameters ) )
{
	$ServerResult = array('Type'=>EPacketType::SummonPremiumCard, 'ServerResult'=>EServerResult::ValidateFailedParameters);
	$ServerResponseData = array('ResponseData'=>array(NULL));
	MakeServerResult($ServerResult, $ServerResponseData);
	exit;
}

// Verify Current Player's Card Count.
$CardManager = new CardManager($CSVManager, $AccountManager);
$MaximumCardCount = $AccountManager->GetPlayerCardMaxCount( $AccountUniqueID );
$CurrentCardCount = $CardManager->GetCardCount( $AccountUniqueID );

if( $CurrentCardCount >= $MaximumCardCount )
{
	$ServerResult = array('Type'=>EPacketType::SummonPremiumCard, 'ServerResult'=>EServerResult::MaximumCardCount);
	$ServerResponseData = array('ResponseData'=>array(NULL));
	MakeServerResult($ServerResult, $ServerResponseData);
	exit;
}

// Load Default CSV Table.
$ConsumeRua = 0;
if( true == $CSVManager->LoadCSV( DEFAULT_TABLE ) )
{
	$ConsumeRua = $CSVManager->FindCSVValueByCustomNameAndValue( "value", "PSUMMON_NEEDRUA");
}
else
{
	$ConsumeRua = 5;
}

$CardManager = new CardManager($CSVManager, $AccountManager);

// Select Procedure.
$CurrentRua = $AccountManager->GetPlayerRua( $AccountUniqueID );
if( $ConsumeRua > $CurrentRua )
{
	// 루아가 부족합니다.
	$ServerResult = array('Type'=>EPacketType::SummonPremiumCard, 'ServerResult'=>EServerResult::NotEnoughValue);
	$ServerResponseData = array('ResponseData'=>array($AccountUniqueID));
	MakeServerResult($ServerResult, $ServerResponseData);
}
else if( $CurrentRua >= $ConsumeRua )
{
	// 루아가 충분합니다.
	// 프리미엄 소환 카드 테이블 로딩.
	$PickedCardIndex = 0;
	$LevelTableIndex = 0;
	$PickedLevel = 0;

	if( true == $CSVManager->LoadCSV( PREMIUM_SUMMON_TABLE ) )
	{
		$SummonPremiumTable = $CSVManager->GetCSVData();
		if( NULL != $SummonPremiumTable )
		{
			//var_dump($SummonPremiumTable);

			// CSV 테이블의 모든 확률을 더한다. 모두 더한 값이 랜덤 범위가 된다.
			$SumOfRate = 0;
			$TableCount = count($SummonPremiumTable);
			for( $Counter = 0; $Counter < $TableCount; ++$Counter )
			{
				$SumOfRate += $SummonPremiumTable[$Counter]['Rate'];
			}

	    	$RandValue = GetRandValue( 1, $SumOfRate );
	    	//echo "RandValue : " . $RandValue;
			//echo "<br />";

		    $SumOfCurrentRow = 0;
		    for( $Counter = 0; $Counter < $TableCount; ++$Counter )
		    {
		    	// SumOfCurrentRow
		    	$SumOfCurrentRow += $SummonPremiumTable[$Counter]['Rate'];
		    	if( $RandValue < $SumOfCurrentRow )
		    	{
		    		// 카드 하나 골랐다.
		    		$PickedCardIndex = $SummonPremiumTable[$Counter]['Index'];
		    		$LevelTableIndex = $SummonPremiumTable[$Counter]['LvTable'];
		    		//echo "PickedCardIndex : " . $PickedCardIndex;
					//echo "<br />";
					break;
		    	}
		    }

		    // 카드 레벨 테이블을 로드하여, 어떤 레벨을 뽑을지 결정한다.
		    if( true == $CSVManager->LoadCSV(PREMIUM_SUMMON_LEVEL_TABLE))
			{
				//echo "LEVEL TABLE INDEX : " . $LevelTableIndex;
				//echo "<br />";
				$SummonLevelTableRow = $CSVManager->FindCSVValueByIndex($LevelTableIndex);
				if( false != $SummonLevelTableRow )
				{
					$SummonLevelTableRow = array_values($SummonLevelTableRow);
					$SumOfLevelColumns = 0;
					// echo "LEVEL TABLE INDEX : " . $LevelTableIndex;
					// echo "<br />";						
					
					// CSV 안에서 실제 레벨 확률 컬럼은 2번 인덱스부터 마지막까지이므로.
					for( $SubCounter = 2; $SubCounter < count($SummonLevelTableRow);++$SubCounter)
					{
						$SumOfLevelColumns += $SummonLevelTableRow[$SubCounter];
						//echo "<br />";
					}

					//echo "SUM OF LEVEL COLUMNS : " . $SumOfLevelColumns;
					//echo "<br />";
			    	$RandValue = GetRandValue( 0, $SumOfLevelColumns );
			    	$SumOfCurrentRow = 0;
			    	
			    	// CSV 안에서 실제 레벨 확률 컬럼은 2번 인덱스부터 마지막까지이므로.
					for( $SubCounter = 2; $SubCounter < count($SummonLevelTableRow); ++$SubCounter)
					{
						// 단 하나의 확률만을 가지고 있는 경우를 예외 처리함.
						if( 1 == $SumOfLevelColumns )
						{
							if( $SummonLevelTableRow[$SubCounter] == $SumOfLevelColumns )
							{
								$PickedLevel = ($SubCounter-1);
					    		break;		
							}
						}

						// echo "RAND VALUE : " . $RandValue . ", SUM : " . $SumOfCurrentRow . ", CURRENT ROW VALUE : " . $SummonLevelTableRow[$ThirdCounter];
						// echo "<br /><br />";
						$SumOfCurrentRow += $SummonLevelTableRow[$SubCounter];
				    	if( $RandValue < $SumOfCurrentRow )
				    	{
				    		$PickedLevelColumnPosition = $SubCounter;
				    		$SummonLevelTableRow[$SubCounter];
				    		$PickedLevel = ($SubCounter-1);
				    		break;
				    	}
					}
				}
				
			}
		}
	}

	//echo "PICKED CARD INDEX : " . $PickedCardIndex . ", PICKED LEVEL : " . $PickedLevel . ", PICKED LEVEL TABLE COLUMN POSITION : " . $PickedLevelColumnPosition;
	//echo "<br /><br />";

	// 카드와 레벨을 골랐다.
	if( (0 < $PickedCardIndex) && (0 < $PickedLevel) )
	{
		// 플레이어의 루아를 깎는다.
		$ConsumedRua = CalcPlayerValues( EPlayerValues::Rua, ECalcOperation::Minus, $CurrentRua, $ConsumeRua );

		//echo "id : " . $AccountUniqueID . ",  rua : $ConsumedRua<br/>";
		$Update = $AccountManager->UpdateRua( $AccountUniqueID, $ConsumedRua );

		// 플레이어 루아 업데이트.
		$ExecuteResult = DBResultToServerResult($Update['@iResult']);
		if( EServerResult::OK != $ExecuteResult )
		{
			$ResponseData = NULL;
			$ServerResult = EServerResult::DBError;
		}
		else if( EServerResult::OK == $ExecuteResult )
		{
			// 루아 업데이트 성공!
			//echo "PREV RUA : " . $CurrentRua . ", CURRENT RUA : " . $ConsumedRua;

			// 새로 고른 카드를 DB에 넣는다. 레벨을 지정하여.
			$CardManager = new CardManager($CSVManager, $AccountManager);
			$CreateCard = $CardManager->CreateNewCard( $AccountUniqueID, $PickedCardIndex, $PickedLevel );
			if( false == $CreateCard )
			{
				$ResponseData = NULL;
				$ServerResult = EServerResult::DBError;
				// echo "FALSE to Insert Card By Level";
			}
			else if( EServerResult::MaximumCardCount == $CreateCard )
			{
				$ResponseData = NULL;
				$ServerResult = EServerResult::MaximumCardCount;
			}
			else
			{
				$DBResult = $CreateCard["@iResult"];
				$ServerResult = DBResultToServerResult($DBResult);
				if( EServerResult::OK == $ServerResult )
				{
					// 카드 추가 성공.
					$ExecuteResult = array($AccountUniqueID, $PickedCardIndex, ConvertToString($PickedLevel), 
											$CreateCard['@iCardUniqueID'], "$CardManager->PrimiumSummonCardHP",
											"$CardManager->PrimiumSummonCardAttack", "$CardManager->PrimiumSummonCardHeal");

					$LogManager->WriteSummonPremiumCardLog( $AccountUniqueID, $CharacterID, $PickedCardIndex, $PickedLevel );
					$CharType = $CharacterManager->GetCharType( $CharacterID );
					
					// 튜토리얼 소환이라면 튜토리얼 클리어 처리.
					if( 0 == $CharacterManager->GetTutorial($AccountUniqueID, $CharType) )
					{
						$CharacterManager->CompleteTutorial( $AccountUniqueID, $CharacterID );
					}
				}
				else
				{
					$ExecuteResult = array($AccountUniqueID);
				}
			}
		}
	}
	else
	{
		$ExecuteResult = NULL;
		$ServerResult = EServerResult::Error;
	}

	$ServerResult = array('Type'=>EPacketType::SummonPremiumCard, 'ServerResult'=>$ServerResult);
	$ServerResponseData = array('ResponseData'=>$ExecuteResult);
	MakeServerResult($ServerResult, $ServerResponseData);
}
?>