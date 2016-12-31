import os
import sys
import subprocess

def InputProcessInfo():
    processName = raw_input('Input Target Process Full Path: ')
    executeCount = raw_input('Input Execute Count: ')
    return processName, executeCount

def BeginProcess( processName, executeCount ):
    for Counter in range(int(executeCount)):
        os.startfile(processName)

def mainLoop(argv):
    processName = None
    executeCount = None
    infoFileName = "PrevBeginProcess.txt"
    try:
        f = open(infoFileName, "r")
        processName = f.readline().rstrip( '\n' )
        executeCount = f.readline()
        f.close()
        
        question = "find Prev Process Info Name %s, Executecount:%s\nBegin same Process OK? (y/n)"  % (processName, executeCount)
        beginPrevInfo = raw_input(question)

        if 'y' == beginPrevInfo or 'Y' == beginPrevInfo:
            BeginProcess( processName, executeCount )
        else:
            processInfo = InputProcessInfo()

            processName = processInfo[0]
            executeCount = processInfo[1]
    except (IOError) as e:
        processInfo = InputProcessInfo()
        if not processInfo:
            processInfo = InputProcessInfo()

        processName = processInfo[0]
        executeCount = processInfo[1]

    
    f = open(infoFileName, "w")
    f.write(processName+'\n');
    f.write(executeCount)
    f.close()

    BeginProcess( processName, executeCount )

if __name__ == "__main__":
	mainLoop(sys.argv)