# Backlog and open questions

## Open question: PR2 site map salt before PR4

Section 7.3 specifies a generated `storage/tm-sites.php` map containing the current daily `salt` and site configuration. PR2 is required to generate that map immediately on each site mutation, but PR4 is the first unit that introduces the `salts` table and daily rotation/destruction logic. Which format should PR2 write before PR4: omit the `salt` key, write it as `null`, or move a minimal salt source into PR2? PR2 is blocked until this is decided because creating an unrotated or invented salt would violate the privacy design in section 8.
