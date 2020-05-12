#!/usr/bin/python

from pprint import pprint
from beem import Hive
from sys import argv
import json

script, filename, accjson = argv

tmp = open(filename)
customjson = tmp.read()

with open(accjson, 'r') as f:
    postkey = json.load(f)
    account = postkey["account"]
    posting = postkey["posting"]
    
hive = Hive(blocking="head", keys=[posting])
tx = hive.custom_json("1", customjson, required_posting_auths=[account])
t = json.dumps(tx)
print t
