import os
import sys
import ctypes
import platform

class AprilDirectory:
    capacityInfo = { "GB": 1073741824,
                    "MB": 1048576,
                    "KB": 1024,
                    "B": 1 }

    def GetLogicalDriveList(self):
        if 'Windows' == platform.system():
            print 'windows!'

    def GetDiskSize(self, dir, format="MB"):
        dir += '\\'
        if 'Windows' == platform.system():
            totalBytes = ctypes.c_ulonglong(0)
            ctypes.windll.kernel32.GetDiskFreeSpaceExW(ctypes.c_wchar_p(dir), None, ctypes.pointer(totalBytes), None)
            convertedSize = int(totalBytes.value/self.capacityInfo[format.upper()])
            return convertedSize

    def GetDiskFreeSpaceSize(self, dir, format="MB"):
        dir += '\\'
        if 'Windows' == platform.system():
            freeBytes = ctypes.c_ulonglong(0)
            ctypes.windll.kernel32.GetDiskFreeSpaceExW(ctypes.c_wchar_p(dir), None, None, ctypes.pointer(freeBytes))
            convertedSize = int(freeBytes.value/self.capacityInfo[format.upper()])
            return convertedSize

    def GetDirectorySize(self, startpath = '.'):
        totalSize = 0
        for dirpath, dirnames, filenames in os.walk(startpath):
            for eachFile in filenames:
                fp = os.path.join(dirpath, eachFile)
                totalSize += os.path.getsize(fp)
        
        #print(totalSize)

        div = 0
        megaByteValue = int(self.capacityInfo['MB'])
        gigaByteValue = int(self.capacityInfo["GB"])
        kiloByteValue = int(self.capacityInfo["KB"])
        
        if gigaByteValue < totalSize:
            div = (totalSize / gigaByteValue)
            mod = (totalSize % gigaByteValue)
            modMegaByte = (mod/megaByteValue)

            sizeText = "{0}.{1} GB".format(div, modMegaByte)
        elif megaByteValue < totalSize: #and gigaByteValue > totalSize:
            div = (totalSize / megaByteValue)
            mod = (totalSize % megaByteValue)
            modKiloByte = (mod / kiloByteValue)
            sizeText = "{0}.{1} MB".format(div, modKiloByte)
        else:
            div = totalSize/kiloByteValue
            sizeText = "{0} KB".format(div)

        return sizeText
        
if __name__ == "__main__":
    myDirectory = AprilDirectory()

    targetPath = raw_input( 'input dir path: ' )
    dirs = os.listdir( targetPath )

    for file in dirs:
        eachdir = targetPath + file
        dirSize = myDirectory.GetDirectorySize(eachdir)

        print("dir: " + eachdir + ", size: " + dirSize)