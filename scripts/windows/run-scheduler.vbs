' Runs "php artisan schedule:run" without opening a console window.
' Windows Task Scheduler calls this every minute (task "LSPDMS Scheduler");
' Laravel then decides which jobs are due (daily message cleanup, monthly backup).
' Uses the newest PHP inside C:\laragon\bin\php, like the LSPDMS launcher.
Option Explicit

Dim fso, shell, projectDir, phpRoot, phpExe, newest, folder
Set fso = CreateObject("Scripting.FileSystemObject")
Set shell = CreateObject("WScript.Shell")

' This file lives in <project>\scripts\windows\
projectDir = fso.GetParentFolderName(fso.GetParentFolderName(fso.GetParentFolderName(WScript.ScriptFullName)))
phpRoot = "C:\laragon\bin\php"

newest = ""
If fso.FolderExists(phpRoot) Then
    For Each folder In fso.GetFolder(phpRoot).SubFolders
        If fso.FileExists(folder.Path & "\php.exe") Then
            If StrComp(folder.Name, newest, vbTextCompare) > 0 Then newest = folder.Name
        End If
    Next
End If

If newest = "" Then
    phpExe = "php.exe" ' fall back to PHP on PATH
Else
    phpExe = phpRoot & "\" & newest & "\php.exe"
End If

' Routine output ("No scheduled commands are ready") is discarded; the jobs
' themselves record results in storage\logs\laravel.log and the audit table.
shell.CurrentDirectory = projectDir
' 0 = hidden window, True = wait so overlapping runs cannot pile up.
shell.Run "cmd /c """"" & phpExe & """ artisan schedule:run > NUL 2>&1""", 0, True
