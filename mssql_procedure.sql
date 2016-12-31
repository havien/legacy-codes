SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
create PROCEDURE [dbo].[GP_ITEM_AFFIX_GROUP_ITEMTYPE_INSERT]
	(@INDEX					[int]			--기획:분류번호
    ,@AFFIX_GROUP			[int]
	,@TYPE					[tinyint]
	,@SUB_TYPE				[tinyint]
    ,@RETVALUE				[int]OUTPUT)	--반환값

AS

SET NOCOUNT ON

DECLARE @ERRORCODE		[int]
DECLARE @ROWCOUNT		[int]

INSERT [dbo].[TB_ITEM_AFFIX_GROUP_ITEMTYPE]
	   ([INDEX]
	   ,[AFFIX_GROUP]
	   ,[TYPE]
       ,[SUB_TYPE])

VALUES (@INDEX
	   ,@AFFIX_GROUP
	   ,@TYPE
       ,@SUB_TYPE)


SELECT @ERRORCODE=@@ERROR, @ROWCOUNT=@@ROWCOUNT

IF @ERRORCODE <> 0 OR @ROWCOUNT <> 1
	BEGIN
		SET @RETVALUE = -1	--등록실패
	END
ELSE
	BEGIN
		SET @RETVALUE = 0	--등록성공
	END
GO

********************************************/

CREATE PROCEDURE [dbo].[GP_CHARACTER_LOGIN]
	(@CHARACTER_IDX	int output
	,@CHARACTER_NAME nvarchar(16))		--캐릭터이름

AS

SET NOCOUNT ON

SELECT @CHARACTER_IDX=C.[IDX]
  FROM [dbo].[TB_CHARACTER] AS C INNER JOIN [dbo].[TB_CHARACTER_TTL] AS T
    ON C.[IDX]=T.[CHARACTER_IDX]
 WHERE C.[NAME]=@CHARACTER_NAME
   AND C.[APPLY]=0
   AND T.[VALID_THRU]>GETDATE()
GO


SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE PROC [dbo].[GP_PET_SKILL_INSERT_FROM_ITEM]
@IDX INT
,@ATTACK_TYPE TINYINT
,@ITEM_IDX INT
,@RET_VALUE INT OUTPUT
AS

SET NOCOUNT ON

BEGIN TRAN

EXEC GP_PET_SKILL_INSERT @IDX, @ATTACK_TYPE

EXEC GP_INVENTORY_REMOVE_NO_TRAN @ITEM_IDX, @RET_VALUE OUTPUT  
IF @@ERROR <> 0 OR @RET_VALUE <> 0  
BEGIN  
 ROLLBACK TRAN  
 SET @RET_VALUE = 1
 RETURN
END

COMMIT TRAN
GO

DECLARE @TID varchar(100), @Name varchar(100), @PName varchar(100), @ColumnID varchar(100), @Definition varchar(100), @ColumnName varchar(100), @SQLText varchar(700)
DECLARE TNAMECURSOR CURSOR FOR
SELECT [ID], [NAME] FROM SYSOBJECTS WHERE TYPE = 'U' ORDER BY NAME ASC;

OPEN TNAMECURSOR;
FETCH NEXT FROM TNAMECURSOR INTO @TID, @Name;
WHILE 0 = @@FETCH_STATUS
BEGIN
	--PRINT 'TID : ' + @TID;
		DECLARE CURSOR2 CURSOR FOR
			SELECT [NAME], [PARENT_COLUMN_ID], [DEFINITION]
			FROM sys.default_constraints
			WHERE [PARENT_OBJECT_ID] = @TID
		OPEN CURSOR2;
		FETCH NEXT FROM CURSOR2 INTO @PName, @ColumnID, @Definition;
		WHILE 0 = @@FETCH_STATUS
		BEGIN
			DECLARE CURSOR3 CURSOR FOR
				SELECT [NAME] FROM sys.columns WHERE [OBJECT_ID] = @TID AND [COLUMN_ID] = @ColumnID
			OPEN CURSOR3;
			FETCH NEXT FROM CURSOR3 INTO @ColumnName;
			WHILE 0 = @@FETCH_STATUS
			BEGIN
				--PRINT 'Table: ' + @Name + ', Constraint: ' + @PName + ', Value: ' + @Definition + ', ColumnID: ' + @ColumnID + ', ColumnName: ' + @ColumnName;
				SET @SQLText = 'ALTER TABLE ' + @Name + ' ADD CONSTRAINT [' + @PName + '] DEFAULT ' + @Definition + ' FOR [' + @ColumnName + '];';
				PRINT @SQLText;

				FETCH NEXT FROM CURSOR3 INTO @ColumnName;
			END
				
			CLOSE CURSOR3;
			DEALLOCATE CURSOR3;

			FETCH NEXT FROM CURSOR2 INTO @PName, @ColumnID, @Definition;
		END

		CLOSE CURSOR2;
		DEALLOCATE CURSOR2;
	FETCH NEXT FROM TNAMECURSOR INTO @TID, @Name;
END

CLOSE TNAMECURSOR;
DEALLOCATE TNAMECURSOR;
GO

/*
SELECT [ID], [NAME] FROM SYSOBJECTS WHERE TYPE = 'U' ORDER BY NAME ASC;
SELECT * FROM sys.default_constraints where object_id = 1618104805
SELECT * FROM sys.columns where object_id = 1602104748 and column_id = 2
SELECT * FROM sys.objects WHERE type_desc = 'DEFAULT_CONSTRAINT'
*/
