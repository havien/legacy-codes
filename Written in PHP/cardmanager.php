<?php
/**
 * @brief Card Manager [카드에 대한 작업을 수행하는 매니저 클래스]
 * @author Jiyeol Pyo 
 * @date 2013/07/02
 */

include_once "../include/header.php";
include_once "../include/define.php";
include_once "../database/dbmanager.php";
include_once "../include/csvmanager.php";
include_once "../character/charactermanager.php";

class CardManager
{
	private static $CardBaseTableCSVData = NULL;
	private static $CardBaseCSVRow = NULL;
	private static $CardList = NULL;
	private static $CardDeck = NULL;

	private static $mDBManager = NULL;
	private static $mCSVManager = NULL;
	private static $mAccountManager = NULL;

	private static $mMaterialList = array(NULL);
	private static $MaterialUniqueList = NULL;
	private static $mEvolutionColumnNames = NULL;

	// evolution
	public static $ResultCardUniqueID = 0;
	public static $ResultCardIndexID = 0;
	public static $ResultCardLevel = 0;

	// card level.
	private static $mLevelUp = false;
	private static $mCurrentLevel = 0;

	// Latest Card's Ability.
	public static $PrimiumSummonCardHP = 0;
	public static $PrimiumSummonCardAttack = 0;
	public static $PrimiumSummonCardHeal = 0;
	public static $PrimiumSummonCardExperience = 0;

	// SummonRuneTables.
	private static $mRuneSummonTableRows = NULL;

	// 기본으로 최대 소지 가능한 카드 개수.
	private static $mDefaultMaximumCardCount = 0;

	// Reinforce Value's.
	private static $mConsNormalRatio = 0;
	private static $mConsGoodRatio = 0;
	private static $mConsGreatRatio = 0;

	// Getting Reinforce Bonus Ratio's.
	private static $mConsNormalBonus = 0;
	private static $mConsGoodBonus = 0;
	private static $mConsGreatBonus = 0;

	private static $mReinforceMaterialExpBonus = 0;

	private static $mCardSkillUPRatio = 0;

	function CardManager($CSVManager, $AccountManager)
	{
		$this->mDBManager = new DBManager;
		if( false == $this->mDBManager->ConnectToServer(DBSERVER, DBUSER, DBPASSWORD, DATABASE))
		{
			return;
		}

		$this->mCSVManager = $CSVManager;
		$this->mAccountManager = $AccountManager;

		$this->mEvolutionColumnNames = array( "EvolutionMaterial1", "EvolutionMaterial2", 
												"EvolutionMaterial3", "EvolutionMaterial4", "EvolutionMaterial5" );
		$this->mMaterialList = NULL;

		// evolution
		$this->ResultCardUniqueID = 0;
		$this->ResultCardIndexID = 0;
		$this->ResultCardLevel = 0;

		// card level.
		$this->mLevelUp = false;
		$this->CurrentLevel = 0;

		// Load Default CSV Table.
		if( true == $this->mCSVManager->LoadCSV( CARD_BASE_TABLE ) )
		{
			$this->CardBaseTableCSVData = $this->mCSVManager->GetCSVData();
		}

		// Load Default CSV Table.
		if( true == $this->mCSVManager->LoadCSV( DEFAULT_TABLE ) )
		{
			$this->mDefaultMaximumCardCount = $this->mCSVManager->FindCSVValueByCustomNameAndValue( "value", "CREATE_ACCOUNT_CARD_SLOT");
			if( false == $this->mDefaultMaximumCardCount )
			{
				echo "[mDefaultMaximumCardCount] FAILED TO mDefaultMaximumCardCount";
				$this->mDefaultMaximumCardCount = 1000;
			}

			// Getting Reinforce Success Ratㅇio's.
			$this->mConsNormalRatio = $this->mCSVManager->FindCSVValueByCustomNameAndValue( "value", "CONS_NORMAL_RATIO");
			$this->mConsGoodRatio = $this->mCSVManager->FindCSVValueByCustomNameAndValue( "value", "CONS_GOOD_RATIO");
			$this->mConsGreatRatio = $this->mCSVManager->FindCSVValueByCustomNameAndValue( "value", "CONS_GREAT_RATIO");

			// Getting Reinforce Bonus Ratio's.
			$this->mConsNormalBonus = $this->mCSVManager->FindCSVValueByCustomNameAndValue( "value", "CONS_NORMAL_BONUS");
			$this->mConsGoodBonus = $this->mCSVManager->FindCSVValueByCustomNameAndValue( "value", "CONS_GOOD_BONUS");
			$this->mConsGreatBonus = $this->mCSVManager->FindCSVValueByCustomNameAndValue( "value", "CONS_GREAT_BONUS");

			$this->mReinforceMaterialExpBonus = ConvertToFloat($this->mCSVManager->FindCSVValueByCustomNameAndValue( "value", "CONS_TYPE_EXP_BONUS"));

			$this->mCardSkillUPRatio = $this->mCSVManager->FindCSVValueByCustomNameAndValue( "value", "CONS_SKILLUP_RATIO");
			$this->mReinforceBasicCost = $this->mCSVManager->FindCSVValueByCustomNameAndValue( "value", "CONS_DEFAULT_VALUE");
		}
		else
		{
			echo "[mDefaultMaximumCardCount] FAILED TO LOAD DEFAULT CSV TABLE";
			$this->mDefaultMaximumCardCount = 1000;
		}
	}

	function GetDefaultMaximumCardCount()
	{
		return $this->mDefaultMaximumCardCount;
	}

	function GetEvolutionMaterialCount()
	{
		if( NULL == $this->mMaterialList )
		{
			return 0;
		}
		else
		{
			return count( $this->mMaterialList );	
		}
	}

	function LoadCardList( $AccountUniqueID )
	{
		$ProcedureParameters = array($AccountUniqueID);
		if( false == $this->mDBManager->ConnectToServer(DBSERVER, DBUSER, DBPASSWORD, DATABASE))
		{
			return;
		}

		$this->CardList = $this->mDBManager->ExecuteSelectProcedure("SP_SELECT_CARDLIST", $ProcedureParameters);
	}

	function LoadCardDeck( $AccountUniqueID )
	{
		$ProcedureParameters = array($AccountUniqueID);
		if( false == $this->mDBManager->ConnectToServer(DBSERVER, DBUSER, DBPASSWORD, DATABASE))
		{
			return;
		}

		$this->CardDeck = $this->mDBManager->ExecuteSelectProcedure("SP_SELECT_CARD_DECK", $ProcedureParameters);
	}

	function RemoveCardInListByUniqueID( $AccountUniqueID, $UniqueID )
	{
		for( $Counter = 0; $Counter < count( $this->CardList ); ++$Counter )
		{
			if( $UniqueID == $this->CardList[$Counter][0] )
			{
				// delete element of array.
				// 어차피 카드 리스트는 메모리에서 사라지거나 다시 로드하므로 Unique, IndexID를 0으로 한다.
				$this->CardList[$Counter][0] = 0;
				$this->CardList[$Counter][1] = 0;
				return true;
			}
		}

		return false;
	}

	function GetUniqueIDByIndex( $IndexID )
	{
		for( $Counter = 0; $Counter < count( $this->CardList ); ++$Counter )
		{
			if( $IndexID == $this->CardList[$Counter][1] )
			{
				return $this->CardList[$Counter][0];
			}
		}

		return 0;
	}

	function GetLevelByUniqueID( $UniqueID )
	{
		for( $Counter = 0; $Counter < count( $this->CardList ); ++$Counter )
		{
			if( $UniqueID == $this->CardList[$Counter][0] )
			{
				return $this->CardList[$Counter][5];
			}
		}

		return 0;
	}

	function GetExpByUniqueID( $UniqueID )
	{
		for( $Counter = 0; $Counter < count( $this->CardList ); ++$Counter )
		{
			if( $UniqueID == $this->CardList[$Counter][0] )
			{
				return $this->CardList[$Counter][6];
			}
		}

		return 0;
	}

	function GetAbilityByUniqueID( $UniqueID )
	{
		$ReturnArray = NULL;
		for( $Counter = 0; $Counter < count( $this->CardList ); ++$Counter )
		{
			if( $UniqueID == $this->CardList[$Counter][0] )
			{
				$ReturnArray = array($this->CardList[$Counter][7], $this->CardList[$Counter][8], $this->CardList[$Counter][9] );
				return $ReturnArray;
			}
		}

		return 0;
	}

	function GetSkillLevelByUniqueID( $UniqueID )
	{
		for( $Counter = 0; $Counter < count( $this->CardList ); ++$Counter )
		{
			if( $UniqueID == $this->CardList[$Counter][0] )
			{
				return $this->CardList[$Counter][10];
			}
		}

		return 0;
	}

	function RemoveCard( $AccountUniqueID, $UniqueID )
	{
		if( false == $this->mDBManager->ConnectToServer(DBSERVER, DBUSER, DBPASSWORD, DATABASE))
		{
			return;
		}

		$ProcedureParameters = array( $AccountUniqueID, $UniqueID );
		$Result = $this->mDBManager->ExecuteNonSelectProcedure("SP_REMOVE_CARD", $ProcedureParameters);
		if( false == $Result )
		{
			//echo "DB FAILED FROM REMOVE CARD";
			return false;
		}
		else
		{
			return DBResultToServerResult($Result["@iResult"]);
		}
	}

	function RemoveCardByUniqueID( $UniqueID )
	{
		if( false == $this->mDBManager->ConnectToServer(DBSERVER, DBUSER, DBPASSWORD, DATABASE))
		{
			return;
		}

		$ProcedureParameters = array( $UniqueID );
		$Result = $this->mDBManager->ExecuteNonSelectProcedure("SP_REMOVE_CARD_BY_UNIQUE", $ProcedureParameters);
		if( false == $Result )
		{
			//echo "DB FAILED FROM REMOVE CARD";
			return false;
		}
		else
		{
			return DBResultToServerResult($Result["@iResult"]);
		}
	}

	function GetCardCount( $AccountUniqueID )
	{
		if( false == $this->mDBManager->ConnectToServer(DBSERVER, DBUSER, DBPASSWORD, DATABASE))
		{
			return false;
		}

		$ProcedureParameters [] = $AccountUniqueID;

		$CardRow = $this->mDBManager->ExecuteSelectProcedure("SP_SELECT_CARD_COUNT", $ProcedureParameters);
		return $CardRow[0][0];
	}


	function CreateNewCard( $AccountUniqueID, $CardIndex, $Level )
	{
		$CardCount = $this->GetCardCount( $AccountUniqueID );
		// if( $this->mDefaultMaximumCardCount <= $CardCount )
		// {
		// 	return EServerResult::MaximumCardCount;
		// }

		$ServerResult = NULL;
		
		// Calc New Card Ability. 
		$this->PrimiumSummonCardHP = $this->CalcCardAbility( $CardIndex, $Level, ECardAbility::HP );
		$this->PrimiumSummonCardAttack = $this->CalcCardAbility( $CardIndex, $Level, ECardAbility::Attack );
		$this->PrimiumSummonCardHeal = $this->CalcCardAbility( $CardIndex, $Level, ECardAbility::Heal );
		$this->PrimiumSummonCardExperience = $this->CalcCardAbility( $CardIndex, $Level, ECardAbility::Experience );

		if( false == $this->mDBManager->ConnectToServer(DBSERVER, DBUSER, DBPASSWORD, DATABASE))
		{
			return false;
		}

		$ProcedureParameters = array($AccountUniqueID, $CardIndex, $Level,
									$this->PrimiumSummonCardHP, $this->PrimiumSummonCardAttack, $this->PrimiumSummonCardHeal,
									$this->PrimiumSummonCardExperience, '@iCardUniqueID');

		$ExecuteResult = $this->mDBManager->ExecuteNonSelectProcedure("SP_INSERT_CARD_BY_LEVEL", $ProcedureParameters);
		if( false == $ExecuteResult )
		{
			// echo "FALSE to Insert Card By Level";
			return false;
		}
		else
		{
			//return DBResultToServerResult($ExecuteResult["@iResult"]);
			return $ExecuteResult;
		}
	}

	function UpdateCardExpAndLevel( $CardUniqueID, $CardLevel, $CardExp )
	{
		if( false == $this->mDBManager->ConnectToServer(DBSERVER, DBUSER, DBPASSWORD, DATABASE))
		{
			return EServerResult::DBError;
		}

		$ProcedureParameters = array( $CardUniqueID, $CardLevel, $CardExp );
		$Result = $this->mDBManager->ExecuteNonSelectProcedure("SP_UPDATE_CARD_EXP_AND_LEVEL", $ProcedureParameters);
		if( false == $Result )
		{
			return EServerResult::DBFailed;
		}
		else
		{
			return DBResultToServerResult($Result["@iResult"]);
		}
	}

	function UpdateCardAbility( $CardUniqueID, $HP, $Attack, $Heal, $Experience )
	{
		if( false == $this->mDBManager->ConnectToServer(DBSERVER, DBUSER, DBPASSWORD, DATABASE))
		{
			//echo "DB FAILED UPDATE CARD ABILITY DB ERROR";
			return EServerResult::DBError;
		}

		$ProcedureParameters = array( $CardUniqueID, $HP, $Attack, $Heal, $Experience );
		$Result = $this->mDBManager->ExecuteNonSelectProcedure("SP_UPDATE_CARD_ABILITY", $ProcedureParameters);
		if( false == $Result )
		{
			//echo "DB FAILED UPDATE CARD ABILITY";
			return EServerResult::DBFailed;
		}
		else
		{
			return DBResultToServerResult($Result["@iResult"]);
		}
	}

	function UpdateCardSkillLevel( $CardUniqueID, $SkillLevel )
	{
		if( false == $this->mDBManager->ConnectToServer(DBSERVER, DBUSER, DBPASSWORD, DATABASE))
		{
			//echo "DB FAILED UPDATE CARD ABILITY DB ERROR";
			return EServerResult::DBError;
		}

		$ProcedureParameters = array( $CardUniqueID, $SkillLevel );
		$Result = $this->mDBManager->ExecuteNonSelectProcedure("SP_UPDATE_CARD_SKILL_LEVEL", $ProcedureParameters);
		if( false == $Result )
		{
			return EServerResult::DBFailed;
		}
		else
		{
			return DBResultToServerResult($Result["@iResult"]);
		}
	}

	function CalcCostHaveCards( array $CardUniqueList )
	{
		$SumOfCardCost = 0;
		for( $Counter = 0; $Counter < count( $CardUniqueList ); ++$Counter )
		{
			$CardCost = 0;
			if( 0 < $CardUniqueList[$Counter] )
			{
				$CardCost = $this->GetCardCostByUniqueID( $CardUniqueList[$Counter] );
			}
			
			$SumOfCardCost += $CardCost;
		}

		return $SumOfCardCost;
		
	}

	function CalcCostHaveOnDeckCards( $AccountUniqueID )
	{
		$SumOfCardCost = 0;
		if( NULL == $this->CardDeck )
		{
			$this->LoadCardDeck( $AccountUniqueID );	
		}
		
		for( $Counter = 0; $Counter < count( $this->CardDeck ); ++$Counter )
		{
			$CardCost = 0;
			if( 0 < $this->CardDeck[$Counter] )
			{
				$CardCost = $this->GetCardCostByUniqueID( $this->CardDeck[$Counter][0] );
			}
			
			$SumOfCardCost += $CardCost;
		}

		return $SumOfCardCost;
	}

	function CalcCostOfSellCard( $CardIndex, $CardLevel )
	{
		$BasePrice = $this->GetCardBasePrice( $CardIndex );
		return ($CardLevel * $BasePrice);
	}

	function OnCardDeck( $AccountUniqueID, $CardUniqueID )
	{
		if( false == ValidateArray( $this->CardDeck ) )
		{
			return false;
		}

		for( $Counter = 0; $Counter < count( $this->CardDeck ); ++$Counter )
		{
			if( $CardUniqueID == $this->CardDeck[$Counter][0] )
			{
				return true;
			}
		}

		return false;
	}

	function GetDeckPosition( $AccountUniqueID, $CardUniqueID )
	{
		for( $Counter = 0; $Counter < count( $this->CardDeck ); ++$Counter )
		{
			if( $CardUniqueID == $this->CardDeck[$Counter][0] )
			{
				return $this->CardDeck[$Counter][1];
			}
		}

		return 0;
	}

	function ValidateMaterialCard()
	{
		$MaterialCount = count( $this->mMaterialList );
		for( $Counter = 0; $Counter < $MaterialCount; ++$Counter )
		{
			if( false == $this->ContainCardByIndexID( $this->mMaterialList[ $Counter ] ) )
			{
				return false;
			}
		}

		return true;
	}

	function ContainCardByIndexID( $CardIndexID )
	{
		for( $Counter = 0; $Counter < count( $this->CardList ); ++$Counter )
		{
			if( $CardIndexID == $this->CardList[$Counter][1] )
			{
				return true;
			}
		}

		return false;
	}

	function ContainCard( $CardUniqueID )
	{
		for( $Counter = 0; $Counter < count( $this->CardList ); ++$Counter )
		{
			if( $CardUniqueID == $this->CardList[$Counter][0] )
			{
				return true;
			}
		}

		return false;
	}

	function GetCardIndex( $CardUniqueID )
	{
		for( $Counter = 0; $Counter < count( $this->CardList ); ++$Counter )
		{
			if( $CardUniqueID == $this->CardList[$Counter][0] )
			{
				return $this->CardList[$Counter][1];
			}
		}

		return 0;
	}

	function GetCSVCardRow( $CardIndex )
	{
		if( true == $this->mCSVManager->LoadCSV(CARD_BASE_TABLE))
		{
			$this->CardBaseCSVRow = $this->mCSVManager->FindCSVValueByIndex( $CardIndex );
			return true;
		}

		return false;
	}

	function GetEvolutionMaterial( $CardIndex )
	{
		if( false == $this->LoadedCardCSVRow() )
		{
			return false;
		}

		for( $Counter = 0; $Counter < count( $this->mEvolutionColumnNames ); ++$Counter )
		{
			 if( array_key_exists( $this->mEvolutionColumnNames[$Counter], $this->CardBaseCSVRow ) )
			 {
				if( CSVUNUSECOLUMN == $this->CardBaseCSVRow[$this->mEvolutionColumnNames[$Counter]] )
				{
					continue;
				}
			
				$this->mMaterialList [] = $this->CardBaseCSVRow[$this->mEvolutionColumnNames[$Counter]];
			}
		}

		return true;
	}

	function LoadedCardCSVRow()
	{
		if( 0 == count( $this->CardBaseCSVRow ) || NULL == $this->CardBaseCSVRow )
		{
			return false;
		}

		return true;
	}

	function GetCardMaxLevel()
	{
		if( false == $this->LoadedCardCSVRow() )
		{
			return 0;
		}

		if( array_key_exists( "MaxCardLevel", $this->CardBaseCSVRow ) )
		{
			if( CSVUNUSECOLUMN == $this->CardBaseCSVRow["MaxCardLevel"] )
			{
				return 0;
			}

			return $this->CardBaseCSVRow["MaxCardLevel"];
		}
	}

	function FindCSVRowByCardIndex( $Index )
	{
		$ReturnArray = NULL;
		for($Counter = 0; $Counter < count($this->CardBaseTableCSVData); ++$Counter)
		{
			if( $Index == current($this->CardBaseTableCSVData[$Counter]) )
			{
//			   	$ReturnArray[] = array_values($this->CardBaseTableCSVData[$Counter]);
				$ReturnArray[] = $this->CardBaseTableCSVData[$Counter];
				break;
			}
		}

		if( 0 == count($ReturnArray) )
		{
			return false;
		}
		else if( 0 < count( $ReturnArray ) )
		{
			return $ReturnArray[0];
		}
	}

	function GetCardAttribute( $Index )
	{
		$CardBaseRow = $this->FindCSVRowByCardIndex( $Index );
		if( array_key_exists( "Attr1", $CardBaseRow ) )
		{
			if( CSVUNUSECOLUMN == $CardBaseRow["Attr1"] )
			{
				return 0;
			}

			return $CardBaseRow["Attr1"];
		}
	}

	function GetCardExpTable( $Index )
	{
		$CardBaseRow = $this->FindCSVRowByCardIndex( $Index );
		if( array_key_exists( "CardExpTable", $CardBaseRow ) )
		{
			if( CSVUNUSECOLUMN == $CardBaseRow["CardExpTable"] )
			{
				return 0;
			}

			return $CardBaseRow["CardExpTable"];
		}
	}


	function GetMaxLevel( $Index )
	{
		$CardBaseRow = $this->FindCSVRowByCardIndex( $Index );
		if( array_key_exists( "MaxCardLevel", $CardBaseRow ) )
		{
			if( CSVUNUSECOLUMN == $CardBaseRow["MaxCardLevel"] )
			{
				return 0;
			}

			return $CardBaseRow["MaxCardLevel"];
		}
	}


	function GetCardBasePrice( $CardIndex )
	{
		$CardBaseRow = $this->FindCSVRowByCardIndex( $CardIndex );
		if( array_key_exists( "CardBasePrice", $CardBaseRow ) )
		{
			if( CSVUNUSECOLUMN == $CardBaseRow["CardBasePrice"] )
			{
				return 0;
			}

			return $CardBaseRow["CardBasePrice"];
		}
	}

	function GetCardCostByUniqueID( $CardUniqueID )
	{
		$CardIndex = $this->GetCardIndex( $CardUniqueID );
		$CardBaseRow = $this->FindCSVRowByCardIndex( $CardIndex );
		
		if( array_key_exists( "CardCost", $CardBaseRow ) )
		{
			if( CSVUNUSECOLUMN == $CardBaseRow["CardCost"] )
			{
				return 0;
			}

			return $CardBaseRow["CardCost"];
		}	
	}

	function GetEvolutionCost( $CardIndex )
	{
		// Start to Getting Require Evolution Cost.
		$EvolutionMaterialCount = $this->GetEvolutionMaterialCount();
		if( 0 == $EvolutionMaterialCount )
		{
			$this->GetEvolutionMaterial( $CardIndex );
		}

		$EvolutionMaterialCount = $this->GetEvolutionMaterialCount();

		$MaxCardLevel = $this->GetCardMaxLevel( $CardIndex );
		$EvolDefaultValue = 0;

		// Load Default CSV Table.
		if( true == $this->mCSVManager->LoadCSV( DEFAULT_TABLE ) )
		{
			$EvolDefaultValue = $this->mCSVManager->FindCSVValueByCustomNameAndValue( "value", "EVOL_DEFAULT_VALUE");
			if( false == $EvolDefaultValue )
			{
				echo "[GetEvolutionCost] FAILED TO GET EVOLUTION DEFAULT VALUE";
				//return EServerResult::NotFound;
				return false;
			}
		}
		else
		{
			echo "[GetEvolutionCost] FAILED TO LOAD DEFAULT CSV TABLE";
			//return EServerResult::NotFound;
			return false;
		}

		// 진화비용 = 진화 기본 비용(evol_default_value) * 카드 최대 레벨(진화할카드의maxcardlevel) * 진화 소재 개수(evolution material의합)
		//echo "EVOL DEFAULT VALUE : $EvolDefaultValue, MAX CARD LEVEL : $MaxCardLevel, EVOL MATERIAL COUNT : $EvolutionMaterialCount<br />";
		$EvolutionCost = $EvolDefaultValue * $MaxCardLevel * $EvolutionMaterialCount;
		return $EvolutionCost;
	}

	function GetReinforceCost( $CardLevel, $MaterialCardCount )
	{
		$ReinforceCost = ($CardLevel * $this->mReinforceBasicCost * ($MaterialCardCount));
		// echo "REINFORCE COST : " . $ReinforceCost;
		//echo "CARD LEVEL : " . $CardLevel . ", MaterialCount : " . $MaterialCount . ", REINFORCE BASIC COST : " . $this->mReinforceBasicCost ."<br/>";
		return $ReinforceCost;
	}

	function GetEvolutionResultCardIndex()
	{
		if( false == $this->LoadedCardCSVRow() )
		{
			return 0;
		}

		if( array_key_exists( "EvolutionResultsCardIndex", $this->CardBaseCSVRow ) )
		{
			if( CSVUNUSECOLUMN == $this->CardBaseCSVRow["EvolutionResultsCardIndex"] )
			{
				return 0;
			}

			return $this->CardBaseCSVRow["EvolutionResultsCardIndex"];
		}
	}

	function CalcCardAbility( $CardIndex, $CardLevel, $Ability )
	{
		if( 1 > $CardLevel )
		{
			return 0;
		}

		if( 1 > $CardIndex )
		{
			return 0;
		}

		// 1. 타입에 맞는 CSV 로딩.
		$AbilityCSVTable = "";
		switch( $Ability )
		{
			case ECardAbility::HP:
			{
				$AbilityCSVTable = "CardHP.csv";
				break;
			}
			case ECardAbility::Attack:
			{
				$AbilityCSVTable = "CardAtk.csv";
				break;
			}
			case ECardAbility::Heal:
			{
				$AbilityCSVTable = "CardHeal.csv";
				break;
			}
			case ECardAbility::Experience:
			{
				$AbilityCSVTable = "CardExp.csv";
				break;
			}
			default:
			{
				return 0;
			}
		}

		// Load Card Ability CSV Table.
		if( true == $this->mCSVManager->LoadCSV( $AbilityCSVTable ) )
		{
			$CalcAbility = 0;

			if( ECardAbility::Experience != $Ability )
			{
				// 2. 캐릭터 레벨에 맞추어 능력치 테이블의 수치들 주루루룩 더함.
				$AbilityRow = $this->mCSVManager->FindCSVRowsByIndexNew( $CardIndex );
				if( false == $AbilityRow )
				{
					echo "[CalcCardAbility] FAILED TO GET ABILITY ROW<br />";
					//return EServerResult::NotFound;
					return 0;
				}

				// CSV Level1 Start Row Index is 2,
				// in fact, Row Index 2 is Level 1.
				if( 1 == $CardLevel )
				{
					$CalcAbility = $AbilityRow[2];
				}
				else if( 1 < $CardLevel )
				{
					for( $Counter = 2; $Counter < (2+$CardLevel); ++$Counter )
					{
						$value = $AbilityRow[$Counter];
						$CalcAbility += ConvertToInt($value);
					}
				}
			}
			else
			{
				// 해당 카드, 레벨의 최대 경험치를 얻어옴.
				$CardExpTableIndex = $this->GetCardExpTable( $CardIndex );
				$AbilityRow = $this->mCSVManager->FindCSVRowsByIndexNew( $CardExpTableIndex );
				if( false == $AbilityRow )
				{
					echo "[CalcCardAbility] FAILED TO GET ABILITY ROW<br />";
					//return EServerResult::NotFound;
					return 0;
				}
			

				$Realpos = ($CardLevel+2)-1;
				$CalcAbility = $AbilityRow[$Realpos];
				// echo "real pos : " . $Realpos . "<br />";
				// echo "CalcAbility : " . $CalcAbility . "<br />";
			}
			

			return $CalcAbility;
		}
		else
		{
			echo "[CalcCardAbility] FAILED TO LOAD ABILITY CSV TABLE";
			return 0;
		}
	}


	function CalcCardValues( $ValueType, $Operation, $CurrentValue, $Value, $CardIndex, $CardLevel )
	{
		// 해당 카드, 레벨의 최대 경험치를 얻어옴.
		$CardExpTableIndex = $this->GetCardExpTable( $CardIndex );

		// 카드 경험치 테이블에 있는 레벨의 경험치 최대치를 얻음.
		// 카드 경험치 테이블에서 레벨 경험치가 시작되는 위치. 고정값.
		$CardExpTableLevelStartPosition = 1;
		$CardMaxLevel = $this->GetMaxLevel( $CardIndex );
		$CardExpTablePosition = ($CardExpTableLevelStartPosition+$CardMaxLevel);

		$MaxExperience = 0;
		if( true == $this->mCSVManager->LoadCSV( CARD_EXP_TABLE ) )
		{
			$CardExpTableRow = $this->mCSVManager->FindCSVRowsByIndexNew( $CardExpTableIndex );
			$MaxExperience = $CardExpTableRow[$CardExpTablePosition];
		}

		// 해당 수치의 현재 값을 얻어옴.
		$CurrentTargetValue = $CurrentValue;

		// 계산 타입에 따라 최대 값, 최소 값(아직미정) 얻어오고 실제 계산.
		$CalcValue = 0;
		$MinimumMaximumValue = 0;
		if( ECalcOperation::Plus == $Operation )
		{
			$MinimumMaximumValue = $MaxExperience;
			$CalcValue = $CurrentTargetValue + $Value;
			if( $CalcValue >= $MinimumMaximumValue )
			{
				$CalcValue = $MinimumMaximumValue;
			}
		}
		else if( ECalcOperation::Minus == $Operation )
		{
			$MinimumMaximumValue = 0;
			$CalcValue = $CurrentTargetValue - $Value;
			if( 0 >= $CalcValue )
			{
				$CalcValue = $MinimumMaximumValue;
			}
		}

		return $CalcValue;
	}

	function IncreaseExperience( $CardUniqueID, $CardIndex, $Experience )
	{
		$this->mCurrentLevel = 0;
		$this->mLevelUp = false;

		$CurrentLevel = $this->GetLevelByUniqueID( $CardUniqueID );
		$PrevLevel = $CurrentLevel;

		$CurrentExp = $this->GetExpByUniqueID( $CardUniqueID );
		$PrevExp = $CurrentExp;

		//echo "CARD UNIQUE ID : " . $CardUniqueID . ", CARD INDEX : " . $CardIndex . ", CARD LEVEL : " . $CurrentLevel .  ", CURRENT EXP :" . $CurrentExp . ", EXPERIENCE : " . $Experience;

		$IncreasedExperience = $this->CalcCardValues( EPlayerValues::Exp, ECalcOperation::Plus, $CurrentExp, $Experience, $CardIndex, $CurrentLevel );
		//echo "2";

		// 1. CardExpTable인덱스를 찾음.
		// 2. CardExpTable에서 인덱스로 찾아서 rows를 얻음.
		// 3. 만약 LV5의 값이 337이고 경험치가 330인 경우 레벨4, 340인경우 레벨5.
		// 4. DB 업데이트.

		// 카드 경험치 테이블을 로드하여, 레벨 업 조건을 검사한다.
		if( true == $this->mCSVManager->LoadCSV( CARD_BASE_TABLE ) )
		{
			$CardTableRow = $this->mCSVManager->FindCSVValueByIndex( $CardIndex );
			$CurrentCardExpTableIndex = $CardTableRow['CardExpTable'];
			if( true == $this->mCSVManager->LoadCSV( CARD_EXP_TABLE ) )
			{
				$CardMaxLevel = $this->GetCardMaxLevel( $CardIndex );
				$CardExpTableRow = $this->mCSVManager->FindCSVRowsByIndexNew( $CurrentCardExpTableIndex );
				
				$StartPos = 2;
				
				$CalcEndIndex = ($StartPos + $CardMaxLevel);

				// Card Exp Level Start From Row Index 2
				$CalcLevel = 0;

				while( 1 )
				{
					$CalcStartIndex = ($StartPos + $CurrentLevel);
					if( $CalcStartIndex > count( $CardExpTableRow ) )
					{
						break;
					}

					if( $IncreasedExperience <= $CardExpTableRow[$CalcStartIndex] )
					{
						$CalcLevel = ($CalcStartIndex-1);
						break;
					}
					else
					{
						// 현재 경험치가 설정된 현재 레벨의 경험치보다 크다면 레벨 업.
						//echo "LEVEL UP!" . "<br />";
						++$CurrentLevel;
						//++$CalcStartIndex;
					}
				}

				//echo "6";
				//echo "CARD INDEX  : " . $CardIndex . "<br />";

				
				if( $CurrentLevel >= $CardMaxLevel )
				{
					// if already to max card level. cut card exp to max value too.
					$CurrentLevel = $CardMaxLevel;
					$IncreasedExperience = $CardExpTableRow[$CalcEndIndex];
				}
				//echo "7";

				if( $CurrentLevel > $PrevLevel )
				{
					// Levelup Card! Notify
					$this->mLevelUp = true;
					$this->mCurrentLevel = $CurrentLevel;
				}

				// 이미 최대 레벨, 최대 경험치이면 그냥 성공을 리턴한다.
				if( ($PrevLevel == $CurrentLevel) && ($PrevExp == $IncreasedExperience))
				{
					return EServerResult::OK;
				}

				$ServerResult = $this->UpdateCardExpAndLevel( $CardUniqueID, $CurrentLevel, $IncreasedExperience );
				return $ServerResult;
			}
			else
			{
				return EServerResult::NotFound;
			} 
		}
		else
		{
			//echo "IncreaseExperience!! CSV Manager IS NULL";
			return EServerResult::NotFound;
		}
	}

	// 캐릭터 선택 시에 고른 인덱스에 따라 카트 세트를 다르게 내려준다.
	function GetStartCardSet( $SetIndex )
	{
		if( 0 == $SetIndex || empty($SetIndex) )
		{
			return false;
		}

		// 카드 세트 CSV를 로딩하여, 카드와 레벨을 얻은 뒤에 레벨에 맞추어 능력치를 계산하고 DB에 넣는다.
		// 여섯 장째부터는 단순히 카드 리스트에 넣으면 된다.
		if( true == $this->mCSVManager->LoadCSV( START_CARD_SET_TABLE ) )
		{
			$CardSetRow = $this->mCSVManager->FindCSVRowsByIndexNew( $SetIndex );
			if( false == $CardSetRow )
			{
				return false;
			}

			$MaxStartBaseCardCount = 5;
			$CardSetRowCount = 10;//count( $CardSetRow );
			$ReturnCardSetRow = NULL;

			// 기본 카드 다섯장 지급.
			// 시작 카드 세트는 다섯 장이고, 시작 카드들은 덱 위에 올라가야한다. 
			for( $Counter = 1; $Counter <= $MaxStartBaseCardCount; ++$Counter )
			{				
				$CardIndex = $CardSetRow[$Counter];
				// echo "INDEX : $CardIndex<br />";
				$ReturnCardSetRow [] = $CardIndex;
			}

			// index가 쓰여진 row와 기본 카드 다섯장을 제외하고 모두 카드인덱스, 레벨. 로 구성되어 있으므로 루프 돈다.
			// 인덱스-레벨이 한 쌍이므로 +2.
			$Counter = 6;
			while( 1 )
			{
				//echo "COUNTER : $Counter, ROWCOUNT : $CardSetRowCount<br />";
				
				$CardIndex = $CardSetRow[$Counter];
				$CardLevel = $CardSetRow[$Counter+1];

				$ReturnCardSetRow [] = $CardIndex;
				$ReturnCardSetRow [] = $CardLevel;
				// echo "INDEX : $CardIndex<br />";
				// echo "LEVEL : $CardLevel<br />";

				$Counter += 2;

				if( $Counter >= ($CardSetRowCount-1) )
				{
					break;
				}
				
			}
			
			return $ReturnCardSetRow;
		}

		return false;
	}

	function CardOnDeck( $AccountUniqueID, $CardUniqueID, $Position )
	{
		if( false == $this->mDBManager->ConnectToServer(DBSERVER, DBUSER, DBPASSWORD, DATABASE))
		{
			return false;
		}

		$ProcedureParameters = array($AccountUniqueID, $CardUniqueID, $Position);
		$Result = $this->mDBManager->ExecuteNonSelectProcedure("SP_ONDECK_CARD", $ProcedureParameters);
		if( false == $Result )
		{
			return EServerResult::DBError;
		}
		else
		{
			$DBResult = $Result["@iResult"];
			return $ServerResult = DBResultToServerResult($DBResult);
		}

		return EServerResult::DBFailed;
	}

	function ReinforceCard( $AccountUniqueID, $CardUniqueID, $TargetCardIndex, array $MaterialCards )
	{
		/*
		1. 강화에는 카드를 최대 다섯장까지. 강화 재료 카드 검사. 한장이라도 있어야함.
		2. 강화 대상 카드 레벨이 최대 레벨에 도달하면 실패.
		3. 강화 대상 카드 스킬 레벨이 최대 레벨에 도달했거나 스킬이 없을 경우 실패.
		4. 플레이어 보유 골드가 강화 비용보다 같거나 많을 경우 성공.
		*/

		$TargetCardAttribute = $this->GetCardAttribute( $TargetCardIndex );

		// On the Card Deck?
		// if( true == $this->OnCardDeck( $AccountUniqueID, $CardUniqueID ) )
		// {
		// 	//echo "NOW CARD ON DECK";
		// 	return EServerResult::Already;
		// }

		// have Material Cards?
		$HaveMaterialCard = true;
		$MaterialIndexs = NULL;
		$MaterialCardCount = count( $MaterialCards );
		for( $Counter = 0; $Counter < $MaterialCardCount; ++$Counter )
		{
			// On the Card Deck?
			if( true == $this->OnCardDeck( $AccountUniqueID, $MaterialCards[$Counter] ) )
			{
				//echo "NOW CARD ON DECK";
				return EServerResult::Already;
			}

			if( false == $this->ContainCard( $MaterialCards[$Counter] ) )
			{
				//echo "PLAYER NOT HAVE THIS MATERIAL CARD : " . $MaterialCards[$Counter];
				return EServerResult::NotFoundMaterialCard;
			}
			else
			{
				// Get Material Card Index.
				$MaterialIndexs [] = $this->GetCardIndex( $MaterialCards[$Counter] );
			}
		}

		// Validate Target Card Level.
		$TargetCardMaxLevel = $this->GetCardMaxLevel( $TargetCardIndex );
		$TargetCardLevel = $this->GetLevelByUniqueID( $CardUniqueID );
		$TargetCardExp = $this->GetExpByUniqueID( $CardUniqueID );

		if( 0 == $TargetCardLevel )
		{

		}
		
		if( $TargetCardMaxLevel <= $TargetCardLevel )
		{
			//echo "ALREADY MAX LEVEL, TARGET CARD!";
			// 강화 대상 카드 스킬 레벨이 최대 레벨에 도달했거나 스킬이 없을 경우 실패.
			$TargetCardSkillIndex = 0;
			$MaterialCardSkillindexs = NULL;
			$TargetCardSkillIndex = 0;
			$TargetCardMaxSkillLevel = 0;
			$SkillDataTableRow = NULL;

			if( true == $this->mCSVManager->LoadCSV( CARD_BASE_TABLE ) )
			{
				$SkillDataTableRow = $this->mCSVManager->FindCSVRowsByIndexNew( $TargetCardIndex );
				$TargetCardSkillIndex = $SkillDataTableRow[20];
				
				if( true == $this->mCSVManager->LoadCSV( CARD_SKILL_DATA_TABLE ) )
				{
					$SkillDataTableRow = $this->mCSVManager->FindCSVRowsByIndexNew( $TargetCardSkillIndex );
					$TargetCardMaxSkillLevel = $SkillDataTableRow[8];
				}
			}

			$TargetCardSkillLevel = $this->GetSkillLevelByUniqueID( $CardUniqueID );
			if( $TargetCardSkillLevel >= $TargetCardMaxSkillLevel )
			{
				// echo "TARGET CARD SKILL LEVEL :  " . $TargetCardSkillLevel . "<br/ >";
				// echo "TARGET CARD SKILL MAX LEVEL : " . $TargetCardMaxSkillLevel . "<br />";
				return EServerResult::AlreadyMaximum;
			}
			// return EServerResult::AlreadyMaximum;
		}

		$ReinforceCost = $this->GetReinforceCost( $TargetCardLevel, $MaterialCardCount );
		if( 0 >= $ReinforceCost )
		{
			return EServerResult::CalulateError;
		}

		$CurrentGold = $this->mAccountManager->GetPlayerGold( $AccountUniqueID );
		// echo "CURRENT PLAYER'S GOLD : " . $CurrentGold . ", REINFORCE COST : " . $ReinforceCost . "<br />";
		if( $CurrentGold < $ReinforceCost )
		{
			//echo "CURRENT PLAYER'S GOLD : " . $CurrentGold . ", REINFORCE COST : " . $ReinforceCost . "<br />";
			//echo "INSUFFICIENT PLAYER'S GOLD";
			return EServerResult::NotEnoughValue;
		}

		if( $ReinforceCost <= $CurrentGold )
		{
			$SumOfNormalRatio = ($this->mConsNormalRatio + $this->mConsGoodRatio + $this->mConsGreatRatio);
			$RatioRandValue = GetRandValue( 1, $SumOfNormalRatio);

			$PickedRandRatio = 0;
			if( 1 < $RatioRandValue && $this->mConsNormalRatio >= $RatioRandValue )
			{
				$PickedRandRatio = $this->mConsNormalRatio;
			}
			else if( $this->mConsNormalRatio < $RatioRandValue && $this->mConsGoodRatio >= $RatioRandValue )
			{
				$PickedRandRatio = $this->mConsGoodRatio;
			}
			else if( $this->mConsGoodRatio < $RatioRandValue && $this->mConsGreatRatio >= $RatioRandValue )
			{
				$PickedRandRatio = $this->mConsGreatRatio;
			}
			else
			{
				$PickedRandRatio = $this->mConsNormalRatio;
			}

			$SumOfBonusRatio = ($this->mConsNormalBonus + $this->mConsGoodBonus + $this->mConsGreatBonus);
			$BonusRatioRandValue = GetRandValue( 1, $SumOfBonusRatio );

			$PickedRandBonusRatio = 0;
			if( 1 < $BonusRatioRandValue && $this->mConsNormalBonus >= $BonusRatioRandValue )
			{
				$PickedRandBonusRatio = $this->mConsNormalBonus;
			}
			else if( $this->mConsNormalBonus < $BonusRatioRandValue && $this->mConsGoodBonus >= $BonusRatioRandValue )
			{
				$PickedRandBonusRatio = $this->mConsGoodBonus;
			}
			else if( $this->mConsGoodBonus < $BonusRatioRandValue && $this->mConsGreatBonus >= $BonusRatioRandValue )
			{
				$PickedRandBonusRatio = $this->mConsGreatBonus;
			}
			else
			{
				$PickedRandBonusRatio = $this->mConsNormalBonus;
			}
		
			// Load Card Base CSV Table.
			$CalcTotalMaterialExp = 0;
			$TargetCardSkillIndex = 0;
			$MaterialCardSkillindexs = NULL;
			if( true == $this->mCSVManager->LoadCSV( CARD_BASE_TABLE ) )
			{
				$CalcTotalMaterialExp = 0;
				$SkillDataTableRow = $this->mCSVManager->FindCSVRowsByIndexNew( $TargetCardIndex );
				$TargetCardSkillIndex = $SkillDataTableRow[20];

				// Calc Material Card's EnhanceBase Exp.
				for( $Counter = 0; $Counter < $MaterialCardCount; ++$Counter )
				{
					$SkillDataTableRow = $this->mCSVManager->FindCSVRowsByIndexNew( $MaterialIndexs[$Counter] );
					$MaterialCardSkillindexs [] = $SkillDataTableRow[20];

					// Get Attribute of Material Card.
					$AttributeBonus = 1;
					$MaterialCardAttribute = $this->GetCardAttribute( $MaterialIndexs[$Counter] );
		
					if( $TargetCardAttribute == $MaterialCardAttribute )
					{	
						$AttributeBonus = $this->mReinforceMaterialExpBonus;
					}

					$MaterialCardLevel = $this->GetLevelByUniqueID( $MaterialCards[$Counter] );
					$EnhanceBaseExp = $this->mCSVManager->FindCSVRowsByCustomKey( "EnhanceBaseExp", $MaterialIndexs[$Counter] );
					$EnhanceBaseExp = $EnhanceBaseExp['23'];

					// 소재 카드 레벨 * 강화 소재용 경험치 * 속성 보정치.
					$CalcMaterialExp = ($MaterialCardLevel * $EnhanceBaseExp) * $AttributeBonus;
					$CalcTotalMaterialExp += ConvertToInt($CalcMaterialExp);
					 // echo "ENHANCEBASEEXP : " . $EnhanceBaseExp . ", CALCMATERIALEXP : " .$CalcMaterialExp . ", CALC TOTAL MATERIAL EXP : " . $CalcTotalMaterialExp;
					 // echo "<br />";
				}

				// echo "CALC TOTAL MATERIAL EXP : " . $CalcTotalMaterialExp . "<br />";
				$CalcFinalExp = $TargetCardExp + ($CalcTotalMaterialExp * $PickedRandBonusRatio);
				// echo "CALC FINAL REINFORCE CARD EXP : " . $CalcFinalExp . "<br />";

				//echo "INCREASE EXP, CardUniqueID : " . $CardUniqueID . ", TARGET CARD INDEX : " . $TargetCardIndex . ", FINAL EXP : " . $CalcFinalExp;
				//echo "<br />";
				$ServerResult = $this->IncreaseExperience( $CardUniqueID, $TargetCardIndex, $CalcFinalExp );
				if( EServerResult::OK != $ServerResult )
				{
					return $ServerResult;
				}

				//echo "INCREASE EXP SUCCESS!" . "<br />";

				// if Level up Player.
				$CardHP = 0;
				$CardAttack = 0;
				$CardHeal = 0;
				if( true == $this->mLevelUp )
				{
					/*
					echo "LEVEL UP CARD!" . "<br />";
					echo "PREV LEVEL : " . $TargetCardLevel . "<br />";
					echo "CURRENT LEVEL : " . $this->mCurrentLevel . "<br />";
					*/

					// Calc New Card Ability. 
					$CardHP = $this->CalcCardAbility( $TargetCardIndex, $this->mCurrentLevel, ECardAbility::HP );
					$CardAttack = $this->CalcCardAbility( $TargetCardIndex, $this->mCurrentLevel, ECardAbility::Attack );
					$CardHeal = $this->CalcCardAbility( $TargetCardIndex, $this->mCurrentLevel, ECardAbility::Heal );
					$CardExperience = $this->GetExpByUniqueID( $CardUniqueID );

					/*
					echo "UPDATE CARD ABILITY, HP : " . $CardHP . ", ATTACK : " . $CardAttack . ", HEAL : " . $CardHeal;
					echo "<br />";
					*/

					// and Update to DB.
					$DBResult = $this->UpdateCardAbility( $CardUniqueID, $CardHP, $CardAttack, $CardHeal, $CalcFinalExp );
					$ServerResult = DBResultToServerResult( $DBResult );
					if( EServerResult::OK != $ServerResult )
					{
						return $ServerResult;
					}

					//echo "UPDATE CARD ABILITY SUCCESS!!<br />";
				}

				// Card Skill Level UP.
				$SkillLevel = 0;
				if( true == $this->mCSVManager->LoadCSV( CARD_SKILL_DATA_TABLE ) )
				{
					$SkillDataTableRow = $this->mCSVManager->FindCSVRowsByIndexNew( $TargetCardSkillIndex );
					$TargetCardLvupAbleId = $SkillDataTableRow[4];
					
					$TargetCardSkillLevel = $this->GetSkillLevelByUniqueID( $CardUniqueID );
					$TargetCardMaxSkillLevel = $SkillDataTableRow[8];

					// echo "TargetCardLvupAbleId : " . $TargetCardLvupAbleId . "<br />";
					// echo "TargetCardSkillLevel : " . $TargetCardSkillLevel . "<br />";
					// echo "TargetCardMaxSkillLevel : " . $TargetCardMaxSkillLevel . "<br />";

					$FinalTargetCardSkillLevel = 1;

					for( $Counter = 0; $Counter < $MaterialCardCount; ++$Counter )
					{
						// echo "CURRENT INDEX : " . $MaterialCardSkillindexs[$Counter] . "<br />";
						$SkillDataTableRow = $this->mCSVManager->FindCSVRowsByIndexNew( $MaterialCardSkillindexs[$Counter] );
						// var_dump($SkillDataTableRow) . "<br />";
						$LvupAbleId = $SkillDataTableRow[4];
						
						$MaterialCardSkillLevel = $this->GetSkillLevelByUniqueID( $MaterialCards[$Counter] );
						$MaxSkillLevel = $SkillDataTableRow[8];


					// 	echo "LvupAbleId : " . $LvupAbleId . "<br />";
					// echo "MaterialCardSkillLevel : " . $MaterialCardSkillLevel . "<br />";
					// echo "MaxSkillLevel : " . $MaxSkillLevel . "<br />";
						
						// 강화 소재 카드 별로 액티브 스킬 강화 가능 ID와(LvupAbleId) 강화할 카드의 액티브 스킬 강화 가능 ID과 비교하여
						if( $LvupAbleId == $TargetCardLvupAbleId )
						{
							// echo "LEVELUP OK<BR />";
							// 같은 값일 경우 액티브 스킬 레벨 업 확률로 액티브 스킬 레벨 업 여부를 결정
							$RandValue = GetRandValue( 1, 100 );
						
							$CorrectMinValue = 1;
							$CorrectMaxValue = ($CorrectMinValue + $this->mCardSkillUPRatio);
							
							if( $RandValue >= $CorrectMinValue && $CorrectMaxValue > $RandValue )
							{
								// 스킬 레벨 업.
								// echo "// 강화 카드와 소재 카드의 액티브 스킬 레벨 중 높은 쪽에 +1 함<Br />";
								// 강화 카드와 소재 카드의 액티브 스킬 레벨 중 높은 쪽에 +1 함
								$FinalTargetCardSkillLevel = ++$TargetCardSkillLevel;
								// if( $TargetCardSkillLevel > $MaterialCardSkillLevel )
								// {
									
								// }
								// else
								// {
								// 	$FinalTargetCardSkillLevel = $MaterialCardSkillLevel+1;
								// }
							}
							else
							{
								// 강화 카드와 소재 카드의 액티브 스킬 레벨 중 높은 쪽 레벨을 결과값으로 반영함.
								// if( $TargetCardSkillLevel > $MaterialCardSkillLevel )
								// {
									// echo "/ 강화 카드와 소재 카드의 액티브 스킬 레벨 중 높은 쪽 레벨을 결과값으로 반영함.<Br />";
									$FinalTargetCardSkillLevel = $TargetCardSkillLevel;
								// }
								// else
								// {
								// 	$FinalTargetCardSkillLevel = $MaterialCardSkillLevel;
								// }
							}
						}
					}

					// echo "FinalTargetCardSkillLevel : " . $FinalTargetCardSkillLevel . "<br />";	
				}

				// 레벨 업 후의 액티브 스킬 레벨이 액티브 스킬 최대 레벨보다 클 경우 최대 레벨로 고정시킴.
				if( $FinalTargetCardSkillLevel > $TargetCardMaxSkillLevel )
				{
					// echo "// 레벨 업 후의 액티브 스킬 레벨이 액티브 스킬 최대 레벨보다 클 경우 최대 레벨로 고정시킴.<br />";
					$FinalTargetCardSkillLevel = $TargetCardMaxSkillLevel;
				}
			
				// UPDATE CARD SKILL LEVEL.
				// echo "final skill level : " .$FinalTargetCardSkillLevel;
				$this->UpdateCardSkillLevel( $CardUniqueID, $FinalTargetCardSkillLevel );

				// Remove Material Cards.
				$MaterialCount = count( $MaterialCards );
				for( $Counter = 0; $Counter < $MaterialCardCount; ++$Counter )
				{
					$RemoveCardUniqueID = $MaterialCards[$Counter];
					if( 0 == $RemoveCardUniqueID )
					{
						//echo "FAILED TO GET REMOVE CARD UNIQUE ID!!! CARD INDEX ID : " . $this->mMaterialList[$Counter];
						return EServerResult::NotFoundCard;
					}
					else
					{
						$RemoveCard = $this->RemoveCard( $AccountUniqueID, $RemoveCardUniqueID );
						if( EServerResult::OK != $RemoveCard )
						{
							//echo "FAILED TO REMOVE MATERIAL CARD!!! SERVER RESULT : " . $RemoveCard;
							return $RemoveCard;
						}

						// Remove Current Card for Memory Card List.
						$RemoveCardInCardList = $this->RemoveCardInListByUniqueID( $AccountUniqueID, $RemoveCardUniqueID );
						if( false == $RemoveCardInCardList )
						{
							//echo "FAILED TO REMOVE CARD IN CARD LIST, CARD UNIQUE ID " . $RemoveCardUniqueID . ", CARD INDEX ID : " . $this->mMaterialList[$Counter];
							return EServerResult::Error;
						}
					}
				}

				// Update Player's Gold.
				$CalcGold = CalcPlayerValues( EPlayerValues::Gold, ECalcOperation::Minus, $CurrentGold, $ReinforceCost );
				// GetReinforceCost
				$UpdatedGold = $this->mAccountManager->UpdateGold($AccountUniqueID, $CalcGold);

				if( true == $UpdatedGold )
				{
					$ReturnArray = NULL;
					$ReturnArray [] = ConvertToString($this->mCurrentLevel);

					return $ReturnArray;
				}
				else
				{
					return EServerResult::DBFailed;	
				}
			}
			else
			{
				return EServerResult::FailedToLoadCSVFile;
			}
		}
		else
		{
			return EServerResult::NotEnoughValue;
		}		
	}

	function EvolutionCard( $AccountUniqueID, $CardUniqueID, $CardIndex )
	{
		// 카드 타입이 진화 재료용이 아닐 경우

		// On the Card Deck?
		// if( true == $this->OnCardDeck( $AccountUniqueID, $CardUniqueID ) )
		// {
		// 	//echo "NOW CARD ON DECK";
		// 	return EServerResult::Already;
		// }

		// On the Card Deck, Material Cards?
		for( $Counter = 0; $Counter < count( $this->mMaterialList ); ++$Counter )
		{
			if( true == $this->OnCardDeck( $AccountUniqueID, $this->mMaterialList[$Counter] ) )
			{
				//echo "NOW CARD ON DECK";
				return EServerResult::Already;
			}
		}

		//$DeckPosition = $this->GetDeckPosition( $AccountUniqueID, $CardUniqueID );

		// Get Card CSV Row.
		$getrow = $this->GetCSVCardRow( $CardIndex );
		if( false == $getrow )
		{
			//echo "FAILED TO GET CARD CSV ROW";
			return EServerResult::NotFound;
		}

		// Get Evolution Indexs And Evolution Result Card Index.
		$getMaterial = $this->GetEvolutionMaterial( $CardIndex );
		if( false == $getMaterial )
		{
			//echo "FAILED TO GET EVOLUTION MATERIAL";
			return EServerResult::NotFound;
		}

		$EvolutionResultCardIndex = $this->GetEvolutionResultCardIndex();

		// Validate to Material Card in User's Card List.
		if( false == $this->ValidateMaterialCard() )
		{
			//echo "FAILED TO VALIDATE MATERIAL COUNT";
			return EServerResult::NotEnoughValue;
		}

		// 진화비용 = 진화 기본 비용(evol_default_value) * 카드 최대 레벨(진화할카드의maxcardlevel) * 진화 소재 개수(evolution material의합)
		$EvolutionCost = $this->GetEvolutionCost( $CardIndex );

		// Get Current Player's Gold.
		$CurrentGold = $this->mAccountManager->GetPlayerGold( $AccountUniqueID );
		if( $CurrentGold < $EvolutionCost )
		{
			//echo "CURRENT PLAYER'S GOLD : " . $CurrentGold . ", EVOLUTION COST : " . $EvolutionCost . "<br />";
			//echo "INSUFFICIENT PLAYER'S GOLD";
			return EServerResult::NotEnoughValue;
		}

		if( $EvolutionCost <= $CurrentGold )
		{
			// Get Material Card's UniqueID.
			// Remove Material Cards.
			$MaterialCount = count( $this->mMaterialList );
			for( $Counter = 0; $Counter < $MaterialCount; ++$Counter )
			{
				$RemoveCardUniqueID = $this->GetUniqueIDByIndex( $this->mMaterialList[$Counter] );
				if( 0 == $RemoveCardUniqueID )
				{
					//echo "FAILED TO GET REMOVE CARD UNIQUE ID!!! CARD INDEX ID : " . $this->mMaterialList[$Counter];
					return EServerResult::NotFound;
				}
				else
				{
					$RemoveCard = $this->RemoveCard( $AccountUniqueID, $RemoveCardUniqueID );
					if( EServerResult::OK != $RemoveCard )
					{
						//echo "FAILED TO REMOVE MATERIAL CARD!!! SERVER RESULT : " . $RemoveCard;
						return $RemoveCard;
					}

					// Remove Current Card for Memory Card List.
					$RemoveCardInCardList = $this->RemoveCardInListByUniqueID( $AccountUniqueID, $RemoveCardUniqueID );
					if( false == $RemoveCardInCardList )
					{
						//echo "FAILED TO REMOVE CARD IN CARD LIST, CARD UNIQUE ID " . $RemoveCardUniqueID . ", CARD INDEX ID : " . $this->mMaterialList[$Counter];
						return EServerResult::Error;
					}
				}
			}

			// Remove Target Card.
			$RemoveCardServerResult = $this->RemoveCard( $AccountUniqueID, $CardUniqueID );
			if( EServerResult::OK != $RemoveCard )
			{
				//echo "FAILED TO REMOVE EVOLUTION TARGET CARD!!! SERVER RESULT : " . $RemoveCard;
				return $RemoveCardServerResult;
			}

			// Remove Current Card for Memory Card List.
			// $RemoveCardInCardList = $this->RemoveCardInListByUniqueID( $AccountUniqueID, $CardUniqueID );
			// if( false == $RemoveCardInCardList )
			// {
			// 	echo "FAILED TO REMOVE TARGET CARD IN CARD LIST, TARGET CARD UNIQUE ID " . $CardUniqueID;
			// 	return false;
			// }

			// Create New Material Result Card.
			$EvolDefaultLevel = $this->mCSVManager->FindCSVValueByCustomNameAndValue( "value", "EVOL_DEFAULT_LEVEL");
			$CreateNewCardResult = $this->CreateNewCard( $AccountUniqueID, $EvolutionResultCardIndex, $EvolDefaultLevel );
			$CreateNewCard = DBResultToServerResult($CreateNewCardResult['@iResult']);
			if( EServerResult::OK != $CreateNewCard )
			{
				//echo "FAILED TO CREATE NEW CARD";
				return EServerResult::DBFailed;
			}

			$CalcGold = CalcPlayerValues( EPlayerValues::Gold, ECalcOperation::Minus, $CurrentGold, $EvolutionCost );
			$UpdatedGold = $this->mAccountManager->UpdateGold($AccountUniqueID, $CalcGold);
			if( false == $UpdatedGold )
			{
				//echo "FAILED TO UPDATE PLAYER GOLD!";
				return EServerResult::DBFailed;
			}
			else
			{
				// Reload Card List.
				//echo "SUCCESS";
				// $this->CardList = NULL;
				// $this->LoadCardList( $AccountUniqueID );

				$this->ResultCardUniqueID = $CreateNewCardResult['@iCardUniqueID'];
				$this->ResultCardIndexID = $EvolutionResultCardIndex;
				$this->ResultCardLevel = $EvolDefaultLevel;
				
				// On the Card Deck, Material Cards?
				$ServerResult = EServerResult::OK;
				$DeckPosition = $this->GetDeckPosition( $AccountUniqueID, $CardUniqueID );

				if( 0 < $DeckPosition )
				{
					$ServerResult = $this->CardOnDeck( $AccountUniqueID, $this->ResultCardUniqueID, $DeckPosition );
				}

				return $ServerResult;
			}
		}
		else
		{
			//echo "CURRENT PLAYER'S GOLD : " . $CurrentGold . ", EVOLUTION COST : " . $EvolutionCost . "<br />";
			//echo "INSUFFICIENT PLAYER'S GOLD";
			return EServerResult::NotEnoughValue;
		}
	}

	function SummonCardByRune( $SummonRuneTableIndex )
	{
		if( true == $this->mCSVManager->LoadCSV( RUNE_DROP_TABLE ) )
		{
			$PickedCardIndex = 0;
			$LevelTableIndex = 0;
			$SummonRuneTableRow = $this->mCSVManager->FindCSVRowsByIndexNew( $SummonRuneTableIndex );
			if( NULL != $SummonRuneTableRow )
			{
				$RunesCardCount = $SummonRuneTableRow[4];
				$RunesRank = $SummonRuneTableRow[5];

				// CSV ROW의 모든 확률을 더한다. 모두 더한 값이 랜덤 범위가 된다.
				$SumOfRate = 0;
				$LoopStartPosition = 8; // csv상에서 실제 확률 값이 시작되는 위치.

				// 룬으로 뽑는 카드의 결과물은 랜덤이 아니라 무조건 정해진 한 장이 되므로 이렇게 함.
				$PickedCardIndex = $SummonRuneTableRow[6];
			    $LevelTableIndex = $SummonRuneTableRow[7];

				// csv상에서 하나의 카드는 카드인덱스, 레벨인덱스, 확률의 3가지 값을 가지므로 루프는 인덱스 3씩 뛴다.
				for( $Counter = $LoopStartPosition; $Counter < ($LoopStartPosition+$RunesCardCount); $Counter+=3 )
				{
					$SumOfRate += $SummonRuneTableRow[$Counter];
				}

		    	$RandValue = GetRandValue( 1, $SumOfRate );

			    $SumOfCurrentRow = 0;
			    for( $Counter = $LoopStartPosition; $Counter < ($LoopStartPosition+$RunesCardCount); $Counter+=3 )
			    {
			    	// SumOfCurrentRow
			    	$SumOfCurrentRow += $SummonRuneTableRow[$Counter];
			    	if( $RandValue < $SumOfCurrentRow )
			    	{
			    		// 카드 하나 골랐다.
			    		$PickedCardIndex = $SummonRuneTableRow[($Counter-2)];
			    		$LevelTableIndex = $SummonRuneTableRow[($Counter-1)];
						break;
			    	}
			    }

			    // 카드 레벨 테이블을 로드하여, 어떤 레벨을 뽑을지 결정한다.
			    if( true == $this->mCSVManager->LoadCSV( RUNE_SUMMON_LEVEL_TABLE ) )
				{
					$SummonLevelTableRow = $this->mCSVManager->FindCSVRowsByIndexNew( $LevelTableIndex );
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
						}

						// echo "SUM OF LEVEL COLUMNS : " . $SumOfLevelColumns;
						// echo "<br />";

						// 주사위를 돌려서 카드를 하나 고른다.
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

						   //  		echo "PickedCardIndex : " . $PickedCardIndex;
						   //  		echo "<br />";
						   //  		echo "PickedCardLevel : " . $PickedLevel;
									// echo "<br />";
						    		break;		
								}
							}
							 // echo "RAND VALUE : " . $RandValue . ", SUM : " . $SumOfCurrentRow . ", CURRENT ROW VALUE : " . $SummonLevelTableRow[$SubCounter];
							 // echo "<br /><br />";
							$SumOfCurrentRow += $SummonLevelTableRow[$SubCounter];
					    	if( $RandValue < $SumOfCurrentRow )
					    	{
					    		$PickedLevel = ($SubCounter-1);

					   //  		echo "PickedCardIndex : " . $PickedCardIndex;
					   //  		echo "<br />";
					   //  		echo "PickedCardLevel : " . $PickedLevel;
								// echo "<br />";
					    		break;
					    	}
						}

						// 루프를 다 돌았는데도 못 골랐을 경우, 그냥 첫번째 레벨을 선택한다?
						// 여기로 오면 무조건 무언가를 골랐다.
						$ReturnArray = array($PickedCardIndex, ConvertToString($PickedLevel));
						return $ReturnArray;
					}
					else
					{
						
						return EServerResult::NotFound;
					}
				}
				else
				{
					// echo "csv failed!";
					return EServerResult::NotFound;
				}
			}
		}
		else
		{
			// echo "csv failed!";
			return EServerResult::NotFound;
		}
	}

	function SellCard( $AccountUniqueID, array $SellCardUniques, array $SellCardIndexs, array $SellCardLevels )
	{
		$SellCardCount = count( $SellCardIndexs );
		$CurrentGold = $this->mAccountManager->GetPlayerGoldSimple( $AccountUniqueID );

		// Remove Material Cards.
		$SumOfSellCost = 0;
		$SellCostArray = NULL;
		for( $Counter = 0; $Counter < $SellCardCount; ++$Counter )
		{
			$RemoveCardUniqueID = $SellCardUniques[$Counter];
			if( 0 == $RemoveCardUniqueID )
			{
				//echo "FAILED TO GET REMOVE CARD UNIQUE ID!!! CARD INDEX ID : " . $this->mMaterialList[$Counter];
				return EServerResult::NotFoundCard;
			}
			else
			{
				$RemoveCard = $this->RemoveCardByUniqueID( $RemoveCardUniqueID );
				if( EServerResult::OK != $RemoveCard )
				{
					//echo "FAILED TO REMOVE MATERIAL CARD!!! SERVER RESULT : " . $RemoveCard;
					return $RemoveCard;
				}

				$CostofSellCard = $this->CalcCostOfSellCard( $SellCardIndexs[$Counter], $SellCardLevels[$Counter] );
				$SellCostArray [] = $CostofSellCard;
				$SumOfSellCost += $CostofSellCard;

				// Remove Current Card for Memory Card List.
				$RemoveCardInCardList = $this->RemoveCardInListByUniqueID( $AccountUniqueID, $RemoveCardUniqueID );
				if( false == $RemoveCardInCardList )
				{
					//echo "FAILED TO REMOVE CARD IN CARD LIST, CARD UNIQUE ID " . $RemoveCardUniqueID . ", CARD INDEX ID : " . $this->mMaterialList[$Counter];
					return EServerResult::Error;
				}
			}
		}

		// Update Player's Gold.
		$CalcGold = CalcPlayerValues( EPlayerValues::Gold, ECalcOperation::Plus, $CurrentGold, $SumOfSellCost );
		$UpdatedGold = $this->mAccountManager->UpdateGold($AccountUniqueID, $CalcGold);

		if( true == $UpdatedGold )
		{
			return $SellCostArray;
		}
		else
		{
			return EServerResult::DBFailed;	
		}	
	}
}
?>