Allow the ST to save a version of a character sheet as a reference, to the last version that they have approved
For approvals I should never be able to approve my own items. 
Feature to update the google sheet that displays our map whenever an organization is added, deleted, or editted. Google sheet is https://www.google.com/maps/d/u/1/edit?mid=1ncA-CbSvClCD-AzRvl8Vl342Cs3lSIw&usp=sharing and the SQL query is: SELECT org_name, city, state
FROM organizations
WHERE active = 1
  AND domain IS NOT NULL
  AND domain <> ''
  AND domain NOT LIKE '%VIR%';