<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Pitech\Bundle\PDODblibBundle\Doctrine\DBAL\Platforms;



class PDODblibSQLServer2005Platform extends \Doctrine\DBAL\Platforms\SQLServerPlatform
{
    /**
     * {@inheritDoc}
     */
    public function supportsLimitOffset()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getClobTypeDeclarationSQL(array $field)
    {
        return 'VARCHAR(MAX)';
    }

    /**
     * Obtain DBMS specific SQL code portion needed to set a default value
     * declaration to be used in statements like CREATE TABLE.
     *
     * @param array $field      field definition array
     *
     * @return string           DBMS specific SQL code portion needed to set a default value
     */
    public function getDefaultValueDeclarationSQL($field)
    {
        $default = empty($field['notnull']) ? ' NULL' : '';

        if (isset($field['default'])) {
            $default = " DEFAULT '".$field['default']."'";
            if (isset($field['type'])) {
                if (in_array((string)$field['type'], array("Integer", "BigInteger", "SmallInteger"))) {
                    $default = " DEFAULT ".$field['default'];
                } else if ((string)$field['type'] == 'DateTime' && $field['default'] == $this->getCurrentTimestampSQL()) {
                    $default = " DEFAULT ".$this->getCurrentTimestampSQL();
                } else if ((string) $field['type'] == 'Boolean') {
                    $default = " DEFAULT '" . $this->convertBooleans($field['default']) . "'";
                }
            }
        }

        return $default;
    }

    protected function doModifyLimitQuery($query, $limit, $offset = null)
    {
        if ($limit > 0) {
            if ($offset == 0) {
                $query = preg_replace('/^(SELECT\s(DISTINCT\s)?)/i', '\1TOP ' . $limit . ' ', $query);
            } else {
                $orderby = stristr($query, 'ORDER BY');

                if ( ! $orderby) {
                    $over = 'ORDER BY (SELECT 0)';
                } else {
                    $over = preg_replace('/\"[^,]*\".\"([^,]*)\"/i', '"inner_tbl"."$1"', $orderby);
                }

                // Remove ORDER BY clause from $query
                $query = preg_replace('/\s+ORDER BY(.*)/', '', $query);
                $query = preg_replace('/^SELECT\s/', '', $query);

                $start = $offset + 1;
                $end = $offset + $limit;


                $query = preg_replace('/^DISTINCT\s/', '', $query);
                $query = "SELECT * FROM (SELECT ROW_NUMBER() OVER ($over) AS doctrine_rownum, $query) AS doctrine_tbl WHERE doctrine_rownum BETWEEN $start AND $end";
            }
        }

        return $query;
    }

}

