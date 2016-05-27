# LOG::Miner
A powerful PHP Library which extracts every bit of information it can get from CS2D Logs.

##Changelog

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