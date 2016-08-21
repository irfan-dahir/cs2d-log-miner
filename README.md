# LOG::Miner
A powerful PHP Library which extracts every bit of information it can get from your CounterStrike2D Logs.

##Features
- CS2D server version
- Log file timestamp
- Transfer List files
- Missing Transfer List files
- Map changes
- Server List update requests, updates & failed update requests + timestamps
- Stats Generated
- A separate array for [b]every[/b] player which includes the following
	- Names used
	- IP
	- Ports used
	- USGNs used
	- Connects + timestamp
	- Disconnects + timestamp + reasons (if any)
	- Kills + killed with + timestamp
	- Deaths + timestamp

- Advanced Text searching **NOT IMPLEMENTED YET**
	- Separate by comma to find multiple words
	- Enclose in parenthesis for complete word + exact phrase (case-sensitivity)
	- Use && signs for multiple words/phrases as the AND statement
	- Use || signs for multiple words/phrases as the OR statement
- Use by CLI (command line interface) **NOT IMPLEMENTED YET**
- Outputting/Saving as JSON
- Documentation & Usage Guide **NOT IMPLEMENTED YET**
- Really cool utility functions to make things easier for you! **NOT IMPLEMENTED YET**
- And probably more to come as I mine more gems in the logs

##F.A.Q
**1. What are ***TYPE-1*** and ***TYPE-2*** player arrays and why are some extracted items limited to ***TYPE-1**s?***
The extraction process keys the players *by their **Names***. I know, I know, we can key them by their IPs and call it a day, however this seemed more suitable to me.

Now that we've got past that, if a player joins with a name existing in the increasing player array **AND** he has a different IP, the player array is ***re-organized*** into a numerical indexed array, with the first entry being the first player who used that name and the second (current) entry being the new guy. This is a **TYPE-2** player array.

However if a player name has the same IP as the registered player in the table (it's himself, if you haven't notice that by now) then the stats are simply updated. This is a **TYPE-1** player array.

Now to answer the second part of this question, in **TYPE-1** player arrays, we're 100% sure it's the same guy if he does something like joining a team, killing someone, dying or reconnect so we can simply ***update*** his stats through that. However if it's a **TYPE-2** player array, we ***don't know*** whether it's the same person, so to retain accuracy and bugs we **dont** update some stats.

**TYPE-2 N/A Stats**
1. Kills
2. Deaths
3. Joining CT/TT/Spec
4. Disconnect + Disconnect reasons

So, ***"how can that happen?"***. Lots of people use the name "Player" throughout the game or even fake others, so within the timelimit of the log file (till midnight), many players can use the same name.


##Changelog

###1.5.0.0 alpha
1. `Extract` can now handle an array of file names as well as a single file path or a directory name
2. A much more robust and cleaner Player extraction algorithm & array manager
3. Fixed serverlist update (+requests) bugged timestamp
4. Map Change array now has the value of the map it changed to keyed to the timestamp
5. Added player kills + killed + weapon in use keyed by timestamp
	a. Only available to **TYPE-1** player arrays
5. Added player deaths keyed by timestamp
	a. Only available to **TYPE-1** player arrays
6. The teams the player joined keyed by timestamp
	a. Only available to **TYPE-1** player arrays
7. Stats generated keyed by timestamps


###0.4.0 prototype
1. Added more logging including debug logs
2. added method `validateLogHeader` which checks whether the file loaded for logging is the real thing or not
3. JSON saved files are now named after the log files, with the exception of the extention/filetype
4. Fixed method `scanDirectory` not properly loading files for extraction
5. Added data `disconnect` and `disconnect-reason` for players.
6. `disconnect` and `disconnect-reason` are **NOT** logged for player data TYPE-2 arrays. *Yet*.
7. Log timestamps are now down to the microsecond
8. Added method `ExtractedToJSON` which parses the extracted returning array into JSON. It uses the previous `DataToJSON`. `DataToJSON` only parses the log file array (which is keyed with log unix timestamps) which is *in* the returning extracted array.

###0.3.0.0 prototype
1. Added saving as JSON
2. UTF8 encoding for player names (those weird player names)
3. `LOGGING` AND `VALID_FILETYPE` are now part of the class
4. Added `validFileType()` for proper extension checking
5. Added basic error logging

###0.2.0.0 prototype
1. Major changes in player data types
2. Players are now keyed by player names
3. Different players with same names are registered as associative within the same player name
4. Single player name keyed data types are called TYPE-1s which is a direct array to the respective data
5. Multiple players within same keyed name data types are called TYPE-2s. Respective data arrays are keyed numerically.
6. I have realised that I won't be able to find true player play time because when a player with the same name yet different IP leaves, it's impossible to say that it's the same player thus disconnect can not get logged accurately with player data array TYPE-2s. I have a few work arounds, but they're not complete yet.
7. Namespace not necessary, removed from goals.

###0.1.0.0 prototype
1. initial not-even-alpha release