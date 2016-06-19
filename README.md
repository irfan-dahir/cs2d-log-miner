# LOG::Miner
A powerful PHP Library which extracts every bit of information it can get from your CounterStrike2D Logs.

##Changelog

###0.1.0 not-even-alpha build 4
1. Added more logging including debug logs
2. added method `validateLogHeader` which checks whether the file loaded for logging is the real thing or not
3. JSON saved files are now named after the log files, with the exception of the extention/filetype
4. Fixed method `scanDirectory` not properly loading files for extraction
5. Added data `disconnect` and `disconnect-reason` for players.
6. `disconnect` and `disconnect-reason` are **NOT** logged for player data TYPE-2 arrays. *Yet*.
7. Log timestamps are now down to the microsecond
8. Added method `ExtractedToJSON` which parses the extracted returning array into JSON. It uses the previous `DataToJSON`. `DataToJSON` only parses the log file array (which is keyed with log unix timestamps) which is *in* the returning extracted array.

###0.1.0 not-even-alpha build 3
1. Added saving as JSON
2. UTF8 encoding for player names (those weird player names)
3. `LOGGING` AND `VALID_FILETYPE` are now part of the class
4. Added `validFileType()` for proper extension checking
5. Added basic error logging

###0.1.0 not-even-alpha build 2
1. Major changes in player data types
2. Players are now keyed by player names
3. Different players with same names are registered as associative within the same player name
4. Single player name keyed data types are called TYPE-1s which is a direct array to the respective data
5. Multiple players within same keyed name data types are called TYPE-2s. Respective data arrays are keyed numerically.
6. I have realised that I won't be able to find true player play time because when a player with the same name yet different IP leaves, it's impossible to say that it's the same player thus disconnect can not get logged accurately with player data array TYPE-2s. I have a few work arounds, but they're not complete yet.
7. Namespace not necessary, removed from goals.

###0.1.0 not-even-alpha
1. initial not-even-alpha release