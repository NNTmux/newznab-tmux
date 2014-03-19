# For the threaded scripts you will require the Python cymysql module for mysql:
		# Python 2.*
			sudo apt-get install python-setuptools python-pip
			sudo python -m easy_install pip
			sudo easy_install cymysql
			sudo easy_install pynntp
			sudo easy_install socketpool
			pip list
		# Python 3.* - If Python 3 is installed, the module also must be installed
			sudo apt-get install python3-setuptools python3-pip
			sudo python3 -m easy_install pip
			sudo pip-3.2 install cymysql
			sudo pip-3.2 install pynntp
			sudo pip-3.2 install socketpool
			pip-3.2 list
		# -or-
			sudo pip-3.3 install cymysql
			pip-3.3 list
		#For Ubuntu 13.10, python3 uses pip3, not pip3.2