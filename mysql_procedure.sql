CREATE DEFINER PROCEDURE `SP_ADD_FRIEND`(
        iPlayerUniqueID                 INT,
        iFriendUniqueID                 INT,
        OUT iResult                             INT
)
ERR_TRAN:
BEGIN
        IF 0 = iPlayerUniqueID OR 0 = iFriendUniqueID THEN
                SET iResult = 4;
                LEAVE ERR_TRAN;
        END IF;

        IF iPlayerUniqueID = iFriendUniqueID THEN
                SET iResult = 6;
                LEAVE ERR_TRAN;
        END IF;

        IF EXISTS (SELECT FriendUniqueID FROM `pzm`.`GT_Friend` WHERE PlayerUniqueID = iPlayerUniqueID AND FriendUniqueID = iFriendUniqueID) THEN
                SET iResult = 7;
        ELSE
                START TRANSACTION;
                INSERT INTO `pzm`.`GT_Friend`
                ( PlayerUniqueID, FriendUniqueID )
                VALUES
                ( iPlayerUniqueID, iFriendUniqueID );

                IF 0 < ROW_COUNT() THEN
                        COMMIT;
                        SET iResult = 1;
                ELSE
                        ROLLBACK;
                        SET iResult = 2;
                END IF;
        END IF;
END$$

CREATE PROCEDURE `SP_INCREASE_INVITE_KAKAO_FRIEND_COUNT`(
        iAccountUniqueID                        INT,
        OUT iResult                                     INT
)
ERR_TRAN:
BEGIN
        DECLARE MAX_INVITE_COUNT INT DEFAULT 30;
        DECLARE CurrentInviteCount INT DEFAULT 0;

        (SELECT InviteKakaoFriendCount INTO CurrentInviteCount FROM GT_Account WHERE UniqueID = iAccountUniqueID);
        IF CurrentInviteCount >= MAX_INVITE_COUNT THEN
                SET iResult = 6;
                LEAVE ERR_TRAN;
        END IF;

        START TRANSACTION;
                UPDATE `pzm`.`GT_Account`
                SET
                InviteKakaoFriendCount = (InviteKakaoFriendCount+1)
                WHERE
                UniqueID = iAccountUniqueID;

                IF 0 < ROW_COUNT() THEN
                        COMMIT;
                        SET iResult = 1;
                ELSE
                        ROLLBACK;
                        SET iResult = 2;
                END IF;
        END$$


CREATE DEFINER=`@`192.168.10.120` PROCEDURE `SP_READ_MESSAGE_AND_REWARD_RUA`(
        iAccountUniqueID                        INT,
        iMessageID                                      INT,
        OUT iResult                                     INT
)
ERR_TRAN:
BEGIN
        DECLARE RewardRua INT DEFAULT 0;
        IF EXISTS (SELECT COUNT(UniqueID) FROM `pzm`.`GT_Message` WHERE ReceiverID = iAccountUniqueID AND UniqueID = iMessageID) THEN
                START TRANSACTION;
                UPDATE `pzm`.`GT_Message`
                SET
                `Read` = 1
                WHERE
                UniqueID = iMessageID;

                IF 0 < ROW_COUNT() THEN
                        COMMIT;
                        SET iResult = 1;
                        IF EXISTS (SELECT COUNT(UniqueID) FROM `pzm`.`GT_ProvideRua` WHERE MessageUniqueID = iMessageID AND Received = 0) THEN
                                SELECT Rua INTO RewardRua FROM `pzm`.`GT_ProvideRua` WHERE MessageUniqueID = iMessageID AND Received = 0;
                                CALL SP_UPDATE_ACCOUNT_RUA(iAccountUniqueID, RewardRua, @iResult);
                                IF 1 = @iResult THEN
                                        UPDATE `pzm`.`GT_ProvideRua` SET Received = 1 WHERE MessageUniqueID = iMessageID;
                                        SET iResult = 5;
                                ELSE
                                        SET iResult = 4;
                                END IF;
                        ELSE
                                SET iResult = 3;
                                LEAVE ERR_TRAN;
                        END IF;
                ELSE
                        ROLLBACK;
                        SET iResult = 2;
                END IF;
        ELSE
                SET iResult = 4;
                LEAVE ERR_TRAN;
        END IF;
END$$

CREATE DEFINER=``@`192.168.10.120` PROCEDURE `SP_SELECT_HELPER_LIST`(
        iAccountUniqueID                INT,
        iCharacterUniqueID              INT,
        iSearchRange                    TINYINT
)
BEGIN
        DECLARE CurCharLevel SMALLINT DEFAULT 0;
        SELECT CharLevel INTO CurCharLevel FROM GT_Character WHERE AccountUniqueID = iAccountUniqueID AND UniqueID = iCharacterUniqueiD;

        SELECT Account.UniqueID, Account.NickName, Chara.CharLevel,
                        Card.IndexID, Card.`Level`, Card.SkillLevel,
                        Card.HP, Card.Attack, Card.Heal,
                        Account.LastLoginTime
        FROM
        `pzm`.`GT_Account` as Account
        INNER JOIN
        `pzm`.`GT_Character` as Chara
        INNER JOIN
        `pzm`.`GT_CardList` as Card
        INNER JOIN
        `pzm`.`GT_LeaderCard` as Leader
        ON
        Chara.AccountUniqueID = Account.UniqueID
        AND
        Leader.AccountUniqueID = Account.UniqueID
        AND
        Leader.CardUniqueID = Card.UniqueID
        AND
        (Account.UniqueID != iAccountUniqueID AND Chara.UniqueID != iCharacterUniqueID)
        WHERE
        Chara.CharLevel BETWEEN (CurCharLevel-iSearchRange) AND (CurCharLevel+iSearchRange)
        ORDER BY Chara.LastPlayTime DESC LIMIT 12;
END$$


CREATE DEFINER=``@`192.168.10.120` PROCEDURE `SP_SEND_MESSAGE_AND_REWARD_RUA`(
        iMessageType                    TINYINT(3),
        iSenderID                               VARCHAR(20),
        iReceiverID                             VARCHAR(20),
        iMessage                                CHAR(255),
        iRua                                    SMALLINT,
        OUT iResult                             INT
)
BEGIN
        DECLARE RecentMessageID INT DEFAULT 0;
        START TRANSACTION;
                INSERT INTO `pzm`.`GT_Message`
                ( MessageType, SenderID, ReceiverID, Message )
                VALUES
                ( iMessageType, iSenderID, iReceiverID, iMessage );

                IF 0 < ROW_COUNT() THEN
                        COMMIT;
                        SET iResult = 1;
                        SELECT UniqueID INTO RecentMessageID FROM GT_Message WHERE SenderID = iSenderID AND ReceiverID = iReceiverID ORDER BY SendTime DESC LIMIT 1;

                        START TRANSACTION;
                                INSERT INTO GT_ProvideRua
                                ( MessageUniqueID, Rua )
                                VALUES
                                ( RecentMessageID, iRua );

                                IF 0 < ROW_COUNT() THEN
                                        COMMIT;
                                        SET iResult = 5;
                                ELSE
                                        ROLLBACK;
                                        SET iResult = 4;
                                END IF;
                ELSE
                        ROLLBACK;
                        SET iResult = 2;
                END IF;
END$$


CREATE DEFINER=``@`192.168.10.120` PROCEDURE `SP_UPDATE_RESTORED_STAMINA_TIME`(
        iAccountUniqueID                        INT,
        OUT iResult                                     INT
)
BEGIN
        START TRANSACTION;
                UPDATE `pzm`.`GT_Account` SET RestoredStaminaTime = NOW() WHERE UniqueID = iAccountUniqueID;
                IF 0 < ROW_COUNT() THEN
                        COMMIT;
                        SET iResult = 1;
                ELSE
                        ROLLBACK;
                        SET iResult = 2;
                END IF;
        END$$