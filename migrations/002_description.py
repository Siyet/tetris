# -= О П Ц И И =-
from config import API_URL_BASE, HEADERS
# -=-=-=-=-=-=-=-
# -= Принятые сокращения =-
# sloc - storagelocation
# prod - product
# -=-=-=-=-=-=-=-=-=-=-=-=-
import requests
import json
import csv
import time
import os
import re
from datetime import datetime
from bs4 import BeautifulSoup
# soup = BeautifulSoup(html)
# print soup.get_text()
def _now_str():
    return datetime.strftime(datetime.now(), "%Y-%m-%d %H:%M:%S")

print('"[{0}] Start migration script <{1}>"'.format(_now_str(), os.path.basename(__file__)))
goods = []
response = requests.get('{0}bulk/exports/?type=items&mode=include&fields=sku,ebaydescription,ebay2description,ebay3description,ebay4description,ebay5description'.format(API_URL_BASE), headers=HEADERS)
if response.status_code == 200:
    data = json.loads(response.content.decode('utf-8'))
    if data['result'] != 'success':
        print(data['message'])
        raise
    time.sleep(8)
    _response = requests.get('{0}bulk/exports/{1}'.format(API_URL_BASE, data['export_file']), headers=HEADERS)
    if _response.status_code == 200:
        _data = json.loads(_response.content.decode('utf-8'))
        __response = requests.get(_data['url'])
        if __response.status_code == 200:
            _csv = csv.DictReader(__response.content.decode('utf-8').split('\n')[:-1], delimiter=',', quotechar='"')
            for row in _csv:
                goods.append(row)
print('"[{0}] Recieved {1} goods"'.format(_now_str(), len(goods)))
goods2fix = []
for prod in goods:
    migrate = False
    if prod["ebaydescription"] != "":
        prod["ebaydescription"] = ""
        migrate = True
    if prod["ebay2description"] != "":
        prod["ebay2description"] = ""
        migrate = True
    if prod["ebay3description"] != "":
        prod["ebay3description"] = ""
        migrate = True
    if prod["ebay4description"] != "":
        prod["ebay4description"] = ""
        migrate = True
    if prod["ebay5description"] != "":
        prod["ebay5description"] = ""
        migrate = True
    if migrate:
        goods2fix.append(prod)

print('"[{0}] Migration finished:"'.format(_now_str()))
print('" - goods to update total: {0}"'.format(len(goods2fix)))
# print('" - goods with unexpected data in storagelocation total: {0}"'.format(warn_sloc_count))
i = 0
for prod in goods2fix:
    data = {
        'identifier': 'sku',
        'sku': prod['sku'],
        'ebaydescription': prod['ebaydescription'],
        'ebay2description': prod['ebay2description'],
        'ebay3description': prod['ebay3description'],
        'ebay4description': prod['ebay4description'],
        'ebay5description': prod['ebay5description']
    }
    _response = requests.post('{0}editor/items/edit'.format(API_URL_BASE), headers=HEADERS, data=data)
    if _response.status_code != 200:
        time.sleep(16)
        __response = requests.post('{0}editor/items/edit'.format(API_URL_BASE), headers=HEADERS, data=data)
        if __response.status_code != 200:
            __response.raise_for_status()
    i += 1
    if i%100 == 0:
        print('"[{0}] {1} goods updated of {2}"'.format(_now_str(), i, len(goods2fix)))