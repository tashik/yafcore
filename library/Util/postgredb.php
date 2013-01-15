<?php
/*******************************************************************************************
 *           Author    : Yuriy Rusinov                                                     *
 *           Contact   : rusinov@quasar.ipa.nw.ru                                          *
 *           Copyright : free for non-commercial use.                                      *
 *******************************************************************************************/

    class PostgreDB
    {
        var $host;
        var $username;
        var $password;
        var $port;
        var $dbname;
        var $error = null;
        var $dbconnect;
        var $query;
        var $result;
        var $oid = null;
        var $oid_res;
        var $persistent;

        function PostgreDB ($DB="", $Host="localhost", $PgPort=5432, $User="Anonymous", $pass="Anonymous", $persist=0)
        {
            $this->host=$Host;
            $this->dbname=$DB;
            $this->username=$User;
            $this->password=$pass;
            $this->port=$PgPort;
            $this->persistent=$persist;
            $this->Connect();
        }

        function Connect ()
        {
            $connect="host=".$this->host." port=".$this->port." dbname=".$this->dbname." user=".$this->username;
            if (!empty($this->password))
                $connect.=" password=".$this->password;
            if ($this->persistent)
                $this->dbconnect=pg_pconnect ($connect);
            else
                $this->dbconnect=pg_connect ($connect);
            if (!$this->dbconnect)
                $this->error="cannot connect to database ".$this->dbname;
        }

        function ExecQuery ($sql)
        {
            $this->query=new Query ($sql, $this->dbconnect);
            $this->result=$this->query->Execute();
            $this->error=$this->query->Error();
//            $this->query->Free();
            return $this->result;
        }

        function FetchResult (&$row, $assoc=PGSQL_BOTH)
        {
            if (!$this->error)
            {
                 @$arr=pg_fetch_array ($this->result, $row, $assoc);
                 return $arr;
            }
            else
            {
                echo "An error occured, $this->error";
                return null;
            }
        }

        function NumRows ()
        {
            if ($this->result && !$this->error)
                if (version_compare(phpversion(), "4.2.0", "ge")>0)
                    return pg_num_rows ($this->result);
                else
                    return pg_numrows ($this->result);
            else
                return -1;
        }

        function Error ()
        {
            if (version_compare(phpversion(), "4.2.0", "ge")>0)
                $this->error=pg_last_error ($this->dbconnect);
            return $this->error;
        }

        function Begin ()
        {
            if (version_compare(phpversion(), "4.2.0", "ge")>0)
            {
                pg_query ($this->dbconnect, "begin");
                $this->oid=pg_lo_create ($this->dbconnect);
            }
            else
            {
                pg_exec ($this->dbconnect, "begin");
                $this->oid=pg_locreate ($this->dbconnect);
            }
            $this->result=$this->Open();
            $this->oid_res=$this->result;
        }

        function Create ()
        {
            if (version_compare(phpversion(), "4.2.0", "ge")>0)
                $this->oid=pg_lo_create ($this->dbconnect);
            else
                $this->oid=pg_locreate ($this->dbconnect);
        }

        function Open ($mode="rw")
        {
            if (version_compare(phpversion(), "4.2.0", "ge")>0)
                $this->result=pg_lo_open($this->dbconnect, $this->oid, $mode);
            else
                $this->result=pg_loopen($this->dbconnect, $this->oid, $mode);
            return $this->result;
        }

        function Write ($data)
        {
            if (!$this->oid || $this->error)
                echo "$this->error<br>\n";
            else
                if (version_compare(phpversion(), "4.2.0", "ge")>0)
                    $this->error=pg_lo_write ($this->result, $data);
                else
                    $this->error=pg_lowrite ($this->result, $data);
        }

        function Read ()
        {
            if (!$this->oid)
                echo "$this->error<br>\n";
            else
                if (version_compare(phpversion(), "4.2.0", "ge")>0)
                    $this->result=pg_lo_read_all ($this->result, $data);
                else
                    $this->result=pg_loreadall ($this->result, $data);
            return $this->result;
        }

        function Unlink ()
        {
            if (version_compare(phpversion(), "4.2.0", "ge")>0)
                pg_lo_unlink ($this->dbconnect, $this->oid);
        }

        function LastOID ()
        {
            if (version_compare(phpversion(), "4.2.0", "ge")>0)
                $this->oid=pg_last_oid ($this->result);
            return $this->oid;
        }

        function Close ()
        {
            if (version_compare(phpversion(), "4.2.0", "ge")>0)
            {
                if (!$this->oid)
                    echo "$this->error<br>\n";
                else
                {
                    $this->result=pg_result_status ($this->result);
                    $this->error=pg_lo_close ($this->oid);
                }
            }
        }

        function Options () { return pg_options ($this->dbconnect); }

        function Status ()
        {
            if (version_compare(phpversion(), "4.2.0", "ge")>0)
                return pg_connection_status ($this->dbconnect);
        }

        function RollBack ()
        {
            if (!$this->oid)
                echo "$this->error<br>\n";
            else
                if (version_compare(phpversion(), "4.2.0", "ge")>0)
                    pg_query ($this->dbconnect, "Rollback");
                else
                    pg_exec ($this->dbconnect, "Rollback");
        }

        function Commit ()
        {
            if (!$this->oid)
                echo "$this->error<br>\n";
            else
                if (version_compare(phpversion(), "4.2.0", "ge")>0)
                    pg_query ($this->dbconnect, "Commit");
                else
                    pg_exec ($this->dbconnect, "Commit");
        }

        function DBClose()
        {
            if (!$this->persistent)
                pg_close($this->dbconnect);
        }
    }

    class Query
    {
        var $sql;
        var $result;
        var $field;
        var $dbconnection;
        var $error;
        
        function Query ($sql_q, $dbc )
        {
            $this->sql=$sql_q;
            $this->dbconnection=$dbc;
        }

        function Execute()
        {
            if (version_compare(phpversion(), "4.2.0", "ge")>0)
            {
                $this->result=pg_query ($this->dbconnection, $this->sql);
                $this->error=pg_result_error ($this->result);
            }
            else
                $this->result=pg_exec ($this->dbconnection, $this->sql);
            return $this->result;
        }

        function Error()
        {
            return $this->error;
        }

        function Field($num=0)
        {
           if ($this->result)
               if (version_compare(phpversion(), "4.2.0", "ge")>0)
                   return pg_field_name ($this->result, $num);
               else
                   return pg_fieldname ($this->result, $num);
           else
               return 0;
        }

        function Rows () 
        {
          if ($this->result)
              if (version_compare(phpversion(), "4.2.0", "ge")>0)
                  return pg_num_rows ($this->result);
              else
                  return pg_numrows ($this->result);
          else 
              return 0;
        }

        function Fetch(&$row, $assoc=PGSQL_BOTH)
        {
            if ($this->result)
                $arr=pg_fetch_array ($this->result, $row, $assoc);
            else
                $arr=0;
            return $arr;
        }

        function Free()
        {
            if ($this->result)
                if (version_compare(phpversion(), "4.2.0", "ge")>0)
                    pg_free_result ($this->result);
                else
                    pg_freeresult ($this->result);
        }
    }

?>