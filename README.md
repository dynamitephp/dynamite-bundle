Bundle Configuration:

```yaml
dynamite:
  tables:
    default:
      table_name: '%ddb_table_name%'
      partition_key_name: pk
      sort_key_name: sk
      managed_items:
        - Your\Entity\Name\Goes\Here
      indexes:
        GSI1:
          pk: gsi1pk
          sk: gsi1sk
```