#!/usr/bin/env python

# Written by convict
import sys, os, shutil
import platform, subprocess, re, os, json, urllib2

def runGit(args):
	git_locations = ['git']
	run_dir = os.getcwd()

	if platform.system().lower() == 'darwin':
		git_locations.append('/usr/local/git/bin/git')

	output = err = None
	for cur_git in git_locations:
		cmd = cur_git + ' ' + args
		try:
			p = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, shell=True, cwd=run_dir)
			output, err = p.communicate()
		except OSError:
			print 'Command "%s" did not work. Could not find git.' % cmd
			continue

		if 'not found' in output or 'not recognized as an internal or external command' in output:
			print 'Unable to find git with command "%s"' % cmd
			output = None
		elif 'fatal:' in output or err:
			print 'Git returned bad info. Are you sure this is a git installation?'
			output = None
		elif output:
			break
	return (output, err)

def gitCurrentVersion():
	output, err = runGit('rev-parse HEAD')

	if not output:
		print 'Could not find latest installed version with git.'
		return None

	current_commit = output.strip()

	if not re.match('^[a-z0-9]+$', current_commit):
		print 'Git output does not look like a hash, not using it.'
		return None

	return current_commit

def latestCommit():
	url = 'https://api.github.com/repos/jonnyboy/newznab-tmux/commits/master'
	result = urllib2.urlopen(url).read()
	git = json.JSONDecoder().decode(result)
	return git['sha']

def commitsBehind():
	url = 'https://api.github.com/repos/jonnyboy/newznab-tmux/compare/%s...%s' % (gitCurrentVersion(), latestCommit())
	try:
		result = urllib2.urlopen(url).read()
	except urllib2.HTTPError:
		return None
	git = json.JSONDecoder().decode(result)
	return git['total_commits']

if __name__ == '__main__':
	print 'You are %s commits behind.' % commitsBehind()
