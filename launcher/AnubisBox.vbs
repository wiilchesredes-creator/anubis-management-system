' ============================================
'   ANUBIS BOX — Launcher silencioso
' ============================================
Dim shell, ruta
Set shell = CreateObject("WScript.Shell")
ruta = CreateObject("Scripting.FileSystemObject").GetParentFolderName(WScript.ScriptFullName)
shell.Run """" & ruta & "\AnubisBox_Launcher.bat""", 0, False
Set shell = Nothing
